<?php

declare(strict_types=1);

namespace ReiffIntegrations\Sap\CustomOrderNumber\Command;

use ReiffIntegrations\Sap\CustomOrderNumber\MessageHandler\OrderNumberUpdateMessageHandler;
use ReiffIntegrations\Sap\CustomOrderNumber\Struct\OrderNumberUpdateStruct;
use ReiffIntegrations\Sap\DataAbstractionLayer\ReiffCustomerCollection;
use ReiffIntegrations\Util\Context\DebugState;
use ReiffIntegrations\Util\Context\DryRunState;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

class UpdateCommand extends Command
{
    protected static $defaultName = 'reiff:custom-order-numbers:update';

    public function __construct(
        private readonly OrderNumberUpdateMessageHandler $messageHandler,
        private MessageBusInterface $messageBus,
        private readonly EntityRepository $reiffCustomerRepository
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Update order numbers for customers')
            ->addOption('debug', 'd', InputOption::VALUE_NONE, 'In debug mode, this import runs synchronously and does not write archive files')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'In dry-run, this import does not write to database')
            ->addOption('debtor-number', null, InputOption::VALUE_OPTIONAL, 'Only for specific debtor number');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $debug  = (bool) $input->getOption('debug');
        $dryRun = (bool) $input->getOption('dry-run');
        $style  = new SymfonyStyle($input, $output);
        /** @var null|string $debtorNumber */
        $debtorNumber = $input->getOption('debtor-number');
        $context      = Context::createDefaultContext();

        if ($debug) {
            $context->addState(DebugState::NAME);
            $style->info('Execution is in debug mode');
        }

        if ($dryRun) {
            $context->addState(DryRunState::NAME);
            $style->info('Execution is in dry-run mode');
        }

        $customers = $this->getDebtorsForUpdate($debtorNumber, $context);

        $style->writeln(sprintf('Found %s customers for update', $customers->count()));

        $progressBar = $style->createProgressBar($customers->count());
        $progressBar->setFormat(ProgressBar::FORMAT_DEBUG);

        foreach ($customers->getElements() as $customer) {
            if (empty($customer->getDebtorNumber()) || empty($customer->getCustomerId())) {
                continue;
            }

            $updateStruct = new OrderNumberUpdateStruct(
                $customer->getCustomerId(),
                $customer->getDebtorNumber(),
                $customer->getSalesOrganisation(),
            );

            $message = $this->messageHandler->getMessage($updateStruct, $context);

            if ($context->hasState(DebugState::NAME)) {
                $this->messageHandler->__invoke($message);
            } else {
                $this->messageBus->dispatch($message);
            }

            $progressBar->advance();
        }

        $progressBar->finish();

        return Command::SUCCESS;
    }

    /**
     * @return EntityCollection|ReiffCustomerCollection
     */
    private function getDebtorsForUpdate(?string $debtorNumber, Context $context): EntityCollection
    {
        $customerSearchCriteria = new Criteria();
        $customerSearchCriteria->addFilter(new NotFilter(NotFilter::CONNECTION_OR, [
            new EqualsFilter('debtorNumber', null),
            new EqualsFilter('customerId', null),
        ]));

        if (!empty($debtorNumber)) {
            $customerSearchCriteria->addFilter(new EqualsFilter('debtorNumber', $debtorNumber));
        }

        return $this->reiffCustomerRepository->search($customerSearchCriteria, $context)->getEntities();
    }
}
