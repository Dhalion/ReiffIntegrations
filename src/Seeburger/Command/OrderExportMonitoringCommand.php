<?php

declare(strict_types=1);

namespace ReiffIntegrations\Seeburger\Command;

use ReiffIntegrations\Seeburger\DataAbstractionLayer\OrderExtension;
use ReiffIntegrations\Seeburger\Provider\OrderProvider;
use ReiffIntegrations\Util\Configuration;
use ReiffIntegrations\Util\Mailer;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class OrderExportMonitoringCommand extends Command
{
    protected static $defaultName = 'reiff:order:monitoring';

    private EntityRepository $orderRepository;
    private Mailer $mailer;
    private SystemConfigService $configService;

    public function __construct(
        EntityRepository $orderRepository,
        Mailer $mailer,
        SystemConfigService $configService
    ) {
        parent::__construct();

        $this->orderRepository = $orderRepository;
        $this->mailer          = $mailer;
        $this->configService   = $configService;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $context = Context::createDefaultContext();
        $style   = new SymfonyStyle($input, $output);

        $criteria = new Criteria();
        OrderProvider::setupCriteria($criteria);
        $this->addMonitoringCriteria($criteria);

        $orders = $this->orderRepository->search($criteria, $context);

        $output->writeln(sprintf('Found %s orders', $orders->count()));

        if ($orders->count() > 0) {
            $this->mailer->sendStatusMail(
                [
                    'orders' => $orders,
                ],
                $context
            );

            $data = [];
            $now  = (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT);
            /** @var OrderEntity $order */
            foreach ($orders as $order) {
                $data[] = [
                    'id'                           => $order->getId(),
                    OrderExtension::EXTENSION_NAME => [
                        'notifiedAt' => $now,
                    ],
                ];
            }
            $this->orderRepository->upsert($data, $context);

            $style->success(sprintf('Notified via mail for %d orders', $orders->count()));
        } else {
            $style->success('No orders matching notification criteria');
        }

        return self::SUCCESS;
    }

    protected function configure(): void
    {
        $this->setDescription('Send order monitoring mails');
    }

    private function addMonitoringCriteria(Criteria $criteria): void
    {
        $exportMaxTries            = $this->configService->getInt(Configuration::CONFIG_KEY_ORDER_EXPORT_MAX_ATTEMPTS);
        $notificationBackoffPeriod = $this->configService->getInt(Configuration::CONFIG_KEY_ORDER_EXPORT_MONITORING_PERIOD);

        $criteria
            ->addFilter(
                new EqualsFilter(sprintf('%s.exportedAt', OrderExtension::EXTENSION_NAME), null),
                new OrFilter([
                    new RangeFilter(sprintf('%s.queuedAt', OrderExtension::EXTENSION_NAME), [RangeFilter::GTE => (new \DateTimeImmutable())->sub(new \DateInterval('P1D'))->format(Defaults::STORAGE_DATE_TIME_FORMAT)]),
                    new RangeFilter(sprintf('%s.exportTries', OrderExtension::EXTENSION_NAME), [RangeFilter::GTE => $exportMaxTries]),
                ]),
                new OrFilter([
                    new RangeFilter(sprintf('%s.notifiedAt', OrderExtension::EXTENSION_NAME), [RangeFilter::LTE => (new \DateTimeImmutable())->sub(new \DateInterval(sprintf('P%dD', $notificationBackoffPeriod)))->format(Defaults::STORAGE_DATE_TIME_FORMAT)]),
                    new EqualsFilter(sprintf('%s.notifiedAt', OrderExtension::EXTENSION_NAME), null),
                ]),
            );
    }
}
