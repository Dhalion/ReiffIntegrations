<?php

declare(strict_types=1);

namespace ReiffIntegrations\MeDaPro\ImportHandler;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use ReiffIntegrations\MeDaPro\DataAbstractionLayer\CategoryExtension;
use ReiffIntegrations\MeDaPro\DataProvider\RuleProvider;
use ReiffIntegrations\MeDaPro\Helper\MediaHelper;
use ReiffIntegrations\MeDaPro\Message\CategoryImportMessage;
use ReiffIntegrations\MeDaPro\Struct\CatalogMetadata;
use ReiffIntegrations\MeDaPro\Struct\CatalogStruct;
use ReiffIntegrations\MeDaPro\Struct\ProductsStruct;
use ReiffIntegrations\Util\Configuration;
use ReiffIntegrations\Util\Context\DryRunState;
use ReiffIntegrations\Util\EntitySyncer;
use ReiffIntegrations\Util\Handler\AbstractImportHandler;
use ReiffIntegrations\Util\Mailer;
use ReiffIntegrations\Util\Message\AbstractImportMessage;
use ReiffTheme\ReiffTheme;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Property\PropertyGroupDefinition;
use Shopware\Core\Framework\Api\Sync\SyncOperation;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class CategoryImportHandler extends AbstractImportHandler
{
    private const BATCH_SIZE = 100;
    /** @var string[] */
    private array $categoryIds = [];
    /** @var array<string, bool> */
    private array $updatedCategoryIds = [];

    public function __construct(
        LoggerInterface $logger,
        SystemConfigService $configService,
        Mailer $mailer,
        EntitySyncer $entitySyncer,
        Connection $connection,
        private readonly EntityRepository $categoryRepository,
        private readonly MediaHelper $mediaHelper,
        private readonly RuleProvider $ruleProvider
    ) {
        parent::__construct($logger, $configService, $mailer, $entitySyncer, $connection);
    }

    public function supports(AbstractImportMessage $message): bool
    {
        return $message instanceof CategoryImportMessage;
    }

    /**
     * @param CatalogStruct $struct
     */
    public function getMessage(
        Struct $struct,
        string $archiveFileName,
        CatalogMetadata $catalogMetadata,
        Context $context
    ): CategoryImportMessage
    {
        return new CategoryImportMessage($struct, $archiveFileName, $catalogMetadata, $context);
    }

    public function __invoke(AbstractImportMessage $message): void
    {
        $this->handle($message);
    }

    /**
     * @param CategoryImportMessage $message
     */
    public function handle(AbstractImportMessage $message): void
    {
        $context        = $message->getContext();
        $catalogMetadata = $message->getCatalogMetadata();

        $catalogId      = $message->getCatalogStruct()->getId();
        $sortimentId    = $message->getCatalogStruct()->getSortimentId();
        $rawCategories  = $message->getCatalogStruct()->getCategories();

        $rootCategoryId = $this->configService->getString(Configuration::CONFIG_KEY_ROOT_CATEGORY);

        $categoryIdSw6IdMapping = [];
        foreach ($rawCategories->getElements() as $rawCategory) {
            $categoryIdSw6IdMapping[$rawCategory->getId()] = $this->getCategoryId($rawCategory->getUId(), $context);
        }

        $preparedCategoryCount = 0;
        foreach ($rawCategories->getElements() as $rawCategory) {
            $categoryData = [
                'id'                     => $categoryIdSw6IdMapping[$rawCategory->getId()],
                'swagDynamicAccessRules' => $this->getDynamicAccessRules($sortimentId, $context),
            ];

            $updateKey = md5(
                CategoryDefinition::ENTITY_NAME .
                $categoryData['id'].
                $catalogMetadata->getLanguageCode()
            );

            if (!array_key_exists($updateKey, $this->updatedCategoryIds)) {
                $parentId = $rootCategoryId;

                if ($rawCategory->getParentId() !== null) {
                    $parentId = $categoryIdSw6IdMapping[$rawCategory->getParentId()];
                }

                $categoryData = array_merge($categoryData, [
                    'parentId'  => $parentId,
                    'active'    => true,
                    'cmsPageId' => $parentId === $rootCategoryId
                        ? $this->configService->getString(Configuration::CONFIG_KEY_CATEGORY_MAIN_CMS_PAGE)
                        : $this->configService->getString(Configuration::CONFIG_KEY_CATEGORY_NORMAL_CMS_PAGE),
                    CategoryExtension::EXTENSION_NAME => [
                        'catalogId' => $catalogId,
                        'uId'       => $rawCategory->getUId(),
                    ],
                    'translations' => [
                        $catalogMetadata->getLanguageCode() => [
                            'name' => $rawCategory->getName(),
                        ],
                    ],
                ]);

                if ($catalogMetadata->isSystemLanguage()) {
                    $categoryMediaPath = $rawCategory->getMediaPaths()['Web Kataloggruppen Hauptbild'] ?? [];

                    if (!empty($categoryMediaPath) && array_key_exists('Web Kataloggruppen Hauptbild', $rawCategory->getMediaPaths())) {
                        $mediaId = $this->mediaHelper->getMediaIdByPath($categoryMediaPath, CategoryDefinition::ENTITY_NAME, $context);

                        if ($mediaId) {
                            $categoryData['customFields'] = [ReiffTheme::THEME_CUSTOM_FIELD_CATEGORY_ICON => $mediaId];
                        } else {
                            $this->addError(new \RuntimeException(sprintf('could not find category media at the location: %s', $categoryMediaPath)), $context);
                        }
                    }
                }
            }

            $this->entitySyncer->addOperation(
                CategoryDefinition::ENTITY_NAME,
                SyncOperation::ACTION_UPSERT,
                $categoryData
            );

            ++$preparedCategoryCount;
            $this->updatedCategoryIds[$updateKey] = true;

            if ($preparedCategoryCount >= self::BATCH_SIZE) {
                if ($context->hasState(DryRunState::NAME)) {
                    dump($this->entitySyncer->getOperations());

                    $this->entitySyncer->reset();
                }

                $this->entitySyncer->flush($context);
                $preparedCategoryCount = 0;
            }
        }

        if ($preparedCategoryCount > 0) {
            if ($context->hasState(DryRunState::NAME)) {
                dump($this->entitySyncer->getOperations());

                $this->entitySyncer->reset();
            }

            $this->entitySyncer->flush($context);
            $preparedCategoryCount = 0;
        }
    }

    protected function getLogIdentifier(): string
    {
        return self::class;
    }

    private function getCategoryId(string $uId, Context $context): string
    {
        if (!array_key_exists($uId, $this->categoryIds)) {
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter(sprintf('%s.uId', CategoryExtension::EXTENSION_NAME), $uId));

            $categoryId = $this->categoryRepository->searchIds($criteria, $context)->firstId();

            $this->categoryIds[$uId] = $categoryId ?? Uuid::randomHex();
        }

        return $this->categoryIds[$uId];
    }

    private function getDynamicAccessRules(?string $sortimentId, Context $context): array
    {
        $ruleId = $this->ruleProvider->getRuleIdBySortimentId($sortimentId, $context);

        if ($ruleId === null) {
            return [];
        }

        return [
            [
                'id' => $ruleId,
            ],
        ];
    }
}
