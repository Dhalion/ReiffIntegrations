<?php

declare(strict_types=1);

namespace ReiffIntegrations\MeDaPro\Command;

use ReiffIntegrations\MeDaPro\Cleaner\CategoryActivator;
use ReiffIntegrations\MeDaPro\Cleaner\ProductActivator;
use ReiffIntegrations\MeDaPro\Cleaner\SortmentRemoval;
use ReiffIntegrations\MeDaPro\DataProvider\RuleProvider;
use ReiffIntegrations\MeDaPro\Finder\Finder;
use ReiffIntegrations\MeDaPro\Parser\JsonParser;
use ReiffIntegrations\Util\Configuration;
use ReiffIntegrations\Util\Context\DebugState;
use ReiffIntegrations\Util\Context\DryRunState;
use ReiffIntegrations\Util\Context\ForceState;
use ReiffIntegrations\Util\Mailer;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Finder\SplFileInfo;

class CatalogImportCleanupCommand extends Command
{
    protected static $defaultName = 'reiff:import:catalog:cleanup';

    public function __construct(
        private readonly JsonParser $jsonParser,
        private readonly SystemConfigService $systemConfigService,
        private readonly Mailer $mailer,
        private readonly ProductActivator $productActivator,
        private readonly CategoryActivator $categoryActivator,
        private readonly SortmentRemoval $sortmentRemoval,
        private readonly RuleProvider $ruleProvider,
        private readonly Finder $finder,
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
        $context = Context::createDefaultContext();

        $style = new SymfonyStyle($input, $output);

        if ($input->getOption('dry-run')) {
            $context->addState(DryRunState::NAME);
        }

        if ($input->getOption('debug')) {
            $context->addState(DebugState::NAME);
        }

        if ($input->getOption('force')) {
            $context->addState(ForceState::NAME);
        }

        $importBasePath = $this->systemConfigService->getString(Configuration::CONFIG_KEY_FILE_IMPORT_SOURCE_PATH);
        $importFiles    = $this->finder->fetchImportFiles($importBasePath);

        if (empty($importFiles)) {
            $style->info(sprintf('No file found to import at %s', $importBasePath));

            return Command::FAILURE;
        }

        foreach ($importFiles as $importFile) {
            $file            = $importFile->getFile();
            $catalogMetadata = $importFile->getCatalogMetadata();

            if (!$catalogMetadata->isSystemLanguage()) {
                continue;
            }

            $style->info(sprintf('Importing file [%s]', $file->getFilename()));
            $this->removeTrailingComma($file);

            try {
                $style->info('Parsing products');
                $products = $this->jsonParser->getProducts(
                    $file->getRealPath(),
                    $catalogMetadata,
                    $context
                );
            } catch (\Throwable $t) {
                $style->error($t->getMessage());
                $this->mailer->sendErrorMail([$t], $file->getFilename(), $context);

                continue;
            }

            $style->info('Removing sortiment mapping from products');

            $ruleId = $this->ruleProvider->getRuleIdBySortimentId($catalogMetadata->getSortimentId(), $context);
            $this->sortmentRemoval->removeNotIncludedProductSortiments($catalogMetadata->getCatalogId(), $ruleId, $products->getAllProductNumbers());

            $this->cleanUp($style, $context);
        }

        return Command::SUCCESS;
    }

    private function cleanUp(SymfonyStyle $style, Context $context): void
    {
        $activatorErrors = [];
        $style->info('Deactivating variants without assortment');

        try {
            $this->productActivator->deleteVariants($context);
        } catch (\Throwable $throwable) {
            $activatorErrors[] = $throwable;
            $style->error($this->getExceptionAsString($throwable));
        }

        $style->info('Deactivating main products with all variants inactive');

        try {
            $this->productActivator->deactivateMainProducts($context);
        } catch (\Throwable $throwable) {
            $activatorErrors[] = $throwable;
            $style->error($this->getExceptionAsString($throwable));
        }

        $style->info('Deactivating categories with inactive all products');

        try {
            $this->categoryActivator->deactivateCategories($context);
        } catch (\Throwable $throwable) {
            $activatorErrors[] = $throwable;
            $style->error($this->getExceptionAsString($throwable));
        }

        if (count($activatorErrors) > 0) {
            $this->mailer->sendErrorMail($activatorErrors, 'activating/deactivating products/categories', $context);
        }
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

    private function getExceptionAsString(\Throwable $throwable): string
    {
        return sprintf(
            "(%s)\n%s\nFile: %s\n(line %s)",
            $throwable->getCode(),
            $throwable->getMessage(),
            $throwable->getFile(),
            $throwable->getLine()
        );
    }
}
