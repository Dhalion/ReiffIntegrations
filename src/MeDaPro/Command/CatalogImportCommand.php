<?php

declare(strict_types=1);

namespace ReiffIntegrations\MeDaPro\Command;

use K10rIntegrationHelper\Observability\RunService;
use ReiffIntegrations\MeDaPro\Finder\Finder;
use ReiffIntegrations\MeDaPro\Helper\NotificationHelper;
use ReiffIntegrations\MeDaPro\Importer\CategoryImporter;
use ReiffIntegrations\MeDaPro\Importer\ManufacturerImporter;
use ReiffIntegrations\MeDaPro\Importer\MediaImporter;
use ReiffIntegrations\MeDaPro\Importer\PropertyImporter;
use ReiffIntegrations\MeDaPro\ImportHandler\ProductImportHandler;
use ReiffIntegrations\MeDaPro\Message\ProductImportMessage;
use ReiffIntegrations\MeDaPro\Parser\JsonParser;
use ReiffIntegrations\MeDaPro\Struct\ProductStruct;
use ReiffIntegrations\Util\Configuration;
use ReiffIntegrations\Util\Context\DebugState;
use ReiffIntegrations\Util\Context\DryRunState;
use ReiffIntegrations\Util\Context\ForceState;
use ReiffIntegrations\Util\ImportArchiver;
use ReiffIntegrations\Util\LockHandler;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\Messenger\MessageBusInterface;

class CatalogImportCommand extends Command
{
    protected static $defaultName = 'reiff:import:catalog';

    public function __construct(
        private readonly MessageBusInterface $messageBus,
        private readonly JsonParser $jsonParser,
        private readonly SystemConfigService $systemConfigService,
        private readonly LockHandler $lockHandler,
        private readonly CategoryImporter $categoryImporter,
        private readonly ProductImportHandler $productImportHandler,
        private readonly PropertyImporter $propertyImporter,
        private readonly ManufacturerImporter $manufacturerImporter,
        private readonly MediaImporter $mediaImporter,
        private readonly ImportArchiver $archiver,
        private readonly Finder $finder,
        private readonly RunService $runService,
        private readonly NotificationHelper $notificationHelper,
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

        $style = new SymfonyStyle($input, $output);

        if ($dryRun) {
            $context->addState(DryRunState::NAME);
        }

        if ($debug) {
            $context->addState(DebugState::NAME);
        }

        if ($force) {
            $context->addState(ForceState::NAME);
        }

        $style->info('Cleanup temporary archive');
        $this->archiver->cleanup($context);

        $importBasePath = $this->systemConfigService->getString(Configuration::CONFIG_KEY_FILE_IMPORT_SOURCE_PATH);

        try {
            $importFiles = $this->finder->fetchImportFiles($importBasePath);
        } catch (\Throwable $exception) {
            $style->error($exception->getMessage());

            return Command::FAILURE;
        }

        if (empty($importFiles)) {
            $style->info(sprintf('No file found to import at %s', $importBasePath));

            return Command::FAILURE;
        }

        foreach ($importFiles as $importFile) {
            $file            = $importFile->getFile();
            $catalogMetadata = $importFile->getCatalogMetadata();

            $style->info(sprintf('Importing file [%s]', $file->getFilename()));

            if ($this->lockHandler->hasFileLock($file, $context)) {
                $style->info(sprintf('Skipped file [%s] due to existing lock', $file->getFilename()));

                continue;
            }

            $this->lockHandler->createFileLock($file);
            $this->removeTrailingComma($file);

            if (!$context->hasState(DebugState::NAME) && !$context->hasState(DryRunState::NAME)) {
                $archivedFile = $this->archiver->archive($file->getFilename(), $context)->getRealPath();
            } else {
                $archivedFile = $file->getRealPath();
            }

            $catalogMetadata->setArchivedFilename($archivedFile);

            if (!$catalogMetadata->isValid()) {
                $message = sprintf(
                    'Catalog metadata for file %s is invalid: catalogId: %s, languageCode: %s, sortimentId: %s, systemLanguageCode: %s',
                    $catalogMetadata->getArchivedFilename(),
                    $catalogMetadata->getCatalogId(),
                    $catalogMetadata->getLanguageCode(),
                    $catalogMetadata->getSortimentId(),
                    $catalogMetadata->getSystemLanguageCode()
                );

                $style->error($message);

                continue;
            }

            $notificationData = [
                'catalogId'        => $catalogMetadata->getCatalogId(),
                'sortimentId'      => $catalogMetadata->getSortimentId(),
                'language'         => $catalogMetadata->getLanguageCode(),
                'archivedFilename' => $catalogMetadata->getArchivedFilename(),
            ];

            try {
                $style->info('Parsing categories');
                $categoryData = $this->jsonParser->getCategories(
                    $archivedFile,
                    $catalogMetadata
                );

                $style->info('Parsing products');
                $products = $this->jsonParser->getProducts(
                    $archivedFile,
                    $catalogMetadata,
                    $context
                );

                $style->info('Importing categories');
                $this->categoryImporter->importCategories(
                    $categoryData,
                    $catalogMetadata,
                    $context
                );

                $style->info('Importing properties');
                $this->propertyImporter->importProperties(
                    $products,
                    $catalogMetadata,
                    $context
                );

                if ($catalogMetadata->isSystemLanguage()) {
                    $style->info('Importing manufacturers');
                    $this->manufacturerImporter->importManufacturers(
                        $products,
                        $catalogMetadata,
                        $context
                    );

                    $style->info('Importing media');
                    $this->mediaImporter->importMedia(
                        $products,
                        $catalogMetadata,
                        $context
                    );
                }

                $style->info('Importing products');

                $this->runService->createRun(
                    sprintf(
                        'Product Import (%s)',
                        implode('_', array_filter([
                            $catalogMetadata->getSortimentId(),
                            $catalogMetadata->getCatalogId(),
                            $catalogMetadata->getLanguageCode(),
                        ]))
                    ),
                    'product_import',
                    $products->getProducts()->count(),
                    $context
                );

                /** @var ProductStruct $product */
                foreach ($style->progressIterate($products->getProducts()) as $product) {
                    $elementId = Uuid::randomHex();

                    $this->runService->createNewElement(
                        $elementId,
                        $product->getProductNumber(),
                        'product',
                        $context
                    );

                    $productImportMessage = new ProductImportMessage(
                        $product,
                        $catalogMetadata,
                        $context,
                        $elementId
                    );

                    if ($context->hasState(DebugState::NAME)) {
                        $this->productImportHandler->handle($productImportMessage);
                    } else {
                        $this->messageBus->dispatch($productImportMessage);
                    }
                }
            } catch (\Throwable $exception) {
                $notificationData['exception'] = $exception->getMessage();

                $this->notificationHelper->addNotification(
                    'Product Import failed catastrophically',
                    'catalog_import',
                    $notificationData,
                    $catalogMetadata
                );
            }
        }

        $this->notificationHelper->sendNotifications($context);

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
