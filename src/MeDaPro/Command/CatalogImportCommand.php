<?php

declare(strict_types=1);

namespace ReiffIntegrations\MeDaPro\Command;

use ReiffIntegrations\MeDaPro\Command\Context\ImportCommandContext;
use ReiffIntegrations\MeDaPro\ImportHandler\CategoryImportHandler;
use ReiffIntegrations\MeDaPro\ImportHandler\ManufacturerImportHandler;
use ReiffIntegrations\MeDaPro\ImportHandler\MediaImportHandler;
use ReiffIntegrations\MeDaPro\ImportHandler\ProductImportHandler;
use ReiffIntegrations\MeDaPro\ImportHandler\PropertyImportHandler;
use ReiffIntegrations\MeDaPro\Parser\JsonParser;
use ReiffIntegrations\MeDaPro\Struct\ProductStruct;
use ReiffIntegrations\Util\Configuration;
use ReiffIntegrations\Util\Context\DebugState;
use ReiffIntegrations\Util\Context\DryRunState;
use ReiffIntegrations\Util\ImportArchiver;
use ReiffIntegrations\Util\LockHandler;
use ReiffIntegrations\Util\Mailer;
use ReiffIntegrations\Util\MessageHandler\ImportMessageHandler;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\Messenger\MessageBusInterface;

class CatalogImportCommand extends Command
{
    protected static $defaultName = 'reiff:import:catalog';

    public function __construct(
        private readonly MessageBusInterface $messageBus,
        private readonly ImportMessageHandler $messageHandler,
        private readonly JsonParser $jsonParser,
        private readonly SystemConfigService $systemConfigService,
        private readonly LockHandler $lockHandler,
        private readonly CategoryImportHandler $categoryImportHandler,
        private readonly ProductImportHandler $productImportHandler,
        private readonly PropertyImportHandler $propertyImportHandler,
        private readonly ManufacturerImportHandler $manufacturerImportHandler,
        private readonly MediaImportHandler $mediaImportHandler,
        private readonly Mailer $mailer,
        private readonly ImportArchiver $archiver

    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Import categories & products')
            ->addOption('debug', 'd', InputOption::VALUE_NONE, 'In debug mode, this import runs synchronously and does not write archive files')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'In dry-run, this import does not write to database')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'With force, this import does ignore the cache');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $debug   = (bool) $input->getOption('debug');
        $dryRun  = (bool) $input->getOption('dry-run');
        $force   = (bool) $input->getOption('force');
        $context = Context::createDefaultContext();

        $importContext = new ImportCommandContext($debug, $dryRun, $force, $context);

        $style = new SymfonyStyle($input, $output);

        if ($importContext->isDryRun()) {
            $importContext->getContext()->addState(DryRunState::NAME);
        }

        if ($importContext->isDebug()) {
            $importContext->getContext()->addState(DebugState::NAME);
        }

        $style->info('Cleanup temporary archive');
        $this->archiver->cleanup($context);

        $basePath = $this->systemConfigService->getString(Configuration::CONFIG_KEY_FILE_IMPORT_SOURCE_PATH);

        $finder = new Finder();
        $finder->files()->in($basePath);

        if ($finder->hasResults() === false) {
            $style->info(sprintf('No file found to import at %s', $basePath));

            return Command::FAILURE;
        }

        foreach ($finder as $file) {
            $style->info(sprintf('Importing file [%s]', $file->getFilename()));

            if ($this->lockHandler->hasFileLock($file, $importContext)) {
                $style->info(sprintf('Skipped file [%s] due to existing lock', $file->getFilename()));

                continue;
            }

            $this->lockHandler->createFileLock($file);
            $this->removeTrailingComma($file);

            $style->info('Parsing categories');
            $catalogMetadata = $this->jsonParser->getCatalogMetadata($file->getRealPath());
            $categoryData = $this->jsonParser->getCategories($file->getRealPath(), $catalogMetadata);

            if ($categoryData === null) {
                $style->error('Invalid category data provided');
                $archivedFileName = $this->archiver->error($file->getFilename(), $context);

                $this->mailer->sendErrorMail([new \RuntimeException('Invalid category data provided')], $archivedFileName, $importContext->getContext());

                continue;
            }

            try {
                $style->info('Parsing products');
                $products = $this->jsonParser->getProducts($file->getRealPath());
            } catch (\Throwable $t) {
                $style->error($t->getMessage());
                $archivedFileName = $this->archiver->error($file->getFilename(), $context);

                $this->mailer->sendErrorMail([$t], $archivedFileName, $importContext->getContext());

                continue;
            }

            $archivedFileName = $this->archiver->archive($file->getFilename(), $context);

            $categoryImportMessage = $this->categoryImportHandler->getMessage($categoryData, $archivedFileName, $importContext->getContext());

            // We always import categories synchronously to prevent errors for missing categories during product import
            $style->info('Importing categories');
            $this->messageHandler->handle($categoryImportMessage);

            // We always import properties synchronously to prevent errors for duplicate property IDs during product import
            $style->info('Importing properties');
            $this->messageHandler->handle($this->propertyImportHandler->getMessage($products, $archivedFileName, $importContext->getContext()));

            // We always import manufacturers synchronously to prevent errors for duplicate manufacturer IDs during product import
            $style->info('Importing manufacturers');
            $this->messageHandler->handle($this->manufacturerImportHandler->getMessage($products, $archivedFileName, $importContext->getContext()));

            // We always import media synchronously to prevent errors for conflicting media access during product import
            $style->info('Importing media');
            $this->messageHandler->handle($this->mediaImportHandler->getMessage($products, $archivedFileName, $importContext->getContext()));

            $style->info('Importing products');

            /** @var ProductStruct $product */
            foreach ($style->progressIterate($products->getProducts()) as $product) {
                $productImportMessage = $this->productImportHandler->getMessage($product, $archivedFileName, $importContext->getContext());

                if ($importContext->isDebug()) {
                    $this->messageHandler->handle($productImportMessage);
                } else {
                    $this->messageBus->dispatch($productImportMessage);
                }
            }
        }

        return Command::SUCCESS;
    }

    /**
     * The import files sometimes contain invalid json data.
     * Remove trailing commas before import.
     */
    private function removeTrailingComma(SplFileInfo $file): void
    {
        if (PHP_OS_FAMILY === 'Darwin') {
            shell_exec('sed -i \'\' \'s/^,$//g\' ' . escapeshellarg($file->getRealPath()));
        } else {
            shell_exec('sed -i \'s/^,$//g\' ' . escapeshellarg($file->getRealPath()));
        }
    }
}
