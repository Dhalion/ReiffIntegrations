<?php

declare(strict_types=1);

namespace ReiffIntegrations\Seeburger\Command;

use ReiffIntegrations\Sap\DataAbstractionLayer\CustomerExtension;
use ReiffIntegrations\Seeburger\Command\Context\ExportCommandContext;
use ReiffIntegrations\Seeburger\DataAbstractionLayer\OrderExtension;
use ReiffIntegrations\Seeburger\DataAbstractionLayer\ReiffOrderEntity;
use ReiffIntegrations\Seeburger\Provider\OrderProvider;
use ReiffIntegrations\Seeburger\Struct\OrderId;
use ReiffIntegrations\Util\Configuration;
use ReiffIntegrations\Util\Context\DebugState;
use ReiffIntegrations\Util\Context\DryRunState;
use ReiffIntegrations\Util\Handler\AbstractExportHandler;
use ReiffIntegrations\Util\MessageHandler\ExportMessageHandler;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\PrefixFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

class OrderExportCommand extends Command
{
    protected static $defaultName = 'reiff:export:orders';

    private MessageBusInterface $messageBus;
    private ExportMessageHandler $messageHandler;
    private AbstractExportHandler $exportHandler;
    private EntityRepository $orderRepository;
    private SystemConfigService $configService;
    private EntityRepository $reiffOrderRepository;

    public function __construct(
        MessageBusInterface $messageBus,
        ExportMessageHandler $messageHandler,
        AbstractExportHandler $exportHandler,
        EntityRepository $orderRepository,
        SystemConfigService $configService,
        EntityRepository $reiffOrderRepository
    ) {
        parent::__construct();

        $this->messageBus           = $messageBus;
        $this->messageHandler       = $messageHandler;
        $this->exportHandler        = $exportHandler;
        $this->orderRepository      = $orderRepository;
        $this->configService        = $configService;
        $this->reiffOrderRepository = $reiffOrderRepository;
    }

    protected function configure(): void
    {
        $this->setDescription('Export Orders');
        $this->addOption('debug', 'd', InputOption::VALUE_NONE, 'In debug, this export runs synchronously, does not transfer data to Seeburger and throws errors immediately');
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'In dry-run, this export does not write to database (e.g. state transitions, custom fields)');
        $this->addOption('limit', 'l', InputOption::VALUE_OPTIONAL, 'Limit number of exported orders');
        $this->addOption('orderNumber', 'o', InputOption::VALUE_OPTIONAL, 'Export a specific order');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $debug  = (bool) $input->getOption('debug');
        $dryRun = (bool) $input->getOption('dry-run');
        /** @phpstan-ignore-next-line */
        $limit   = $input->getOption('limit') ? (int) $input->getOption('limit') : null;
        $context = Context::createDefaultContext();

        $context = new ExportCommandContext($debug, $dryRun, $limit, $context);

        $style = new SymfonyStyle($input, $output);

        if ($context->isDryRun()) {
            $context->getContext()->addState(DryRunState::NAME);
        }

        if ($context->isDebug()) {
            $context->getContext()->addState(DebugState::NAME);
        }

        /** @phpstan-ignore-next-line */
        $orderNumber = (string) $input->getOption('orderNumber');

        if ($orderNumber) {
            $orderIds = $this->getExportableOrderIdsForOrderNumber($orderNumber, $context->getContext());
        } else {
            $orderIds = $this->getExportableOrderIds($context->getLimit(), $context->getContext());
        }

        if ($orderIds->getTotal() === 0) {
            $output->writeln('No suitable orders found');

            return self::INVALID;
        }

        $style->writeln(sprintf('Found %s orders ready to export', $orderIds->getTotal()));
        $style->progressStart($orderIds->getTotal());

        /** @var string $orderId */
        foreach ($orderIds->getIds() as $orderId) {
            $message = $this->exportHandler->getMessage(new OrderId($orderId), $context->getContext());

            if (!$context->isDryRun()) {
                $orderData = $this->getOrderData($orderId, $context->getContext());
                $this->orderRepository->upsert([
                    [
                        'id'                           => $orderId,
                        OrderExtension::EXTENSION_NAME => [
                            'queuedAt'    => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                            'exportTries' => $orderData->getExportTries() ? $orderData->getExportTries() + 1 : 1,
                        ],
                    ],
                ], $context->getContext());
            }

            if ($context->isDebug()) {
                $iDoc = $this->messageHandler->handleWithResult($message);

                $style->title($orderId);
                $style->text($iDoc);
            } else {
                $this->messageBus->dispatch($message);
            }

            $style->progressAdvance();
        }

        return self::SUCCESS;
    }

    private function getExportableOrderIds(?int $limit, Context $context): IdSearchResult
    {
        $criteria = $this->getOrderCriteria();
        $criteria->addFilter(new PrefixFilter(sprintf('orderCustomer.customer.%s.debtorNumber', CustomerExtension::EXTENSION_NAME), '4'));
        $criteria->addFilter(new EqualsFilter(sprintf('%s.queuedAt', OrderExtension::EXTENSION_NAME), null));
        $criteria->addFilter(new EqualsFilter(sprintf('%s.exportedAt', OrderExtension::EXTENSION_NAME), null));

        $maxExportTries = $this->configService->getInt(Configuration::CONFIG_KEY_ORDER_EXPORT_MAX_ATTEMPTS);
        $criteria->addFilter(
            new MultiFilter(MultiFilter::CONNECTION_OR, [
                new RangeFilter(sprintf('%s.exportTries', OrderExtension::EXTENSION_NAME), [RangeFilter::LT => $maxExportTries]),
                new EqualsFilter(sprintf('%s.exportTries', OrderExtension::EXTENSION_NAME), null),
            ])
        );

        $criteria->setLimit($limit);

        return $this->orderRepository->searchIds($criteria, $context);
    }

    private function getExportableOrderIdsForOrderNumber(string $orderNumber, Context $context): IdSearchResult
    {
        $criteria = $this->getOrderCriteria();
        $criteria->addFilter(new EqualsFilter('orderNumber', $orderNumber));

        return $this->orderRepository->searchIds($criteria, $context);
    }

    private function getOrderCriteria(): Criteria
    {
        $criteria = new Criteria();
        OrderProvider::setupCriteria($criteria);

        return $criteria;
    }

    private function getOrderData(string $orderId, Context $context): ReiffOrderEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('orderId', $orderId));

        return $this->reiffOrderRepository->search($criteria, $context)->first() ?? new ReiffOrderEntity();
    }
}
