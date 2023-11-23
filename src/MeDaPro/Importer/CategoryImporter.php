<?php

declare(strict_types=1);

namespace ReiffIntegrations\MeDaPro\Importer;

use K10rIntegrationHelper\Observability\RunService;
use ReiffIntegrations\MeDaPro\DataAbstractionLayer\CategoryExtension;
use ReiffIntegrations\MeDaPro\DataProvider\RuleProvider;
use ReiffIntegrations\MeDaPro\Helper\MediaHelper;
use ReiffIntegrations\MeDaPro\Helper\NotificationHelper;
use ReiffIntegrations\MeDaPro\Struct\CatalogMetadata;
use ReiffIntegrations\MeDaPro\Struct\CatalogStruct;
use ReiffIntegrations\Util\Configuration;
use ReiffIntegrations\Util\EntitySyncer;
use ReiffTheme\ReiffTheme;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Framework\Api\Sync\SyncOperation;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class CategoryImporter
{
    /** @var string[] */
    private array $categoryIds = [];

    /** @var array<string, bool> */
    private array $updatedCategoryIds = [];

    public function __construct(
        private readonly SystemConfigService $configService,
        private readonly EntitySyncer $entitySyncer,
        private readonly EntityRepository $categoryRepository,
        private readonly MediaHelper $mediaHelper,
        private readonly RuleProvider $ruleProvider,
        private readonly RunService $runService,
        private readonly NotificationHelper $notificationHelper,
    ) {
    }

    public function importCategories(
        CatalogStruct $catalog,
        CatalogMetadata $catalogMetadata,
        Context $context
    ): void {
        $catalogId     = $catalog->getId();
        $sortimentId   = $catalog->getSortimentId();
        $rawCategories = $catalog->getCategories();

        $rootCategoryId = $this->configService->getString(Configuration::CONFIG_KEY_ROOT_CATEGORY);

        $this->runService->createRun(
            sprintf(
                'Category Import (%s)',
                implode('_', array_filter([
                    $catalogMetadata->getSortimentId(),
                    $catalogMetadata->getCatalogId(),
                    $catalogMetadata->getLanguageCode(),
                ]))
            ),
            'category_import',
            $rawCategories->count(),
            $context
        );

        $runStatus = true;

        $notificationData = [
            'catalogId'        => $catalogMetadata->getCatalogId(),
            'sortimentId'      => $catalogMetadata->getSortimentId(),
            'language'         => $catalogMetadata->getLanguageCode(),
            'archivedFilename' => $catalogMetadata->getArchivedFilename(),
        ];

        $categoryIdSw6IdMapping = [];
        foreach ($rawCategories->getElements() as $rawCategory) {
            $categoryIdSw6IdMapping[$rawCategory->getId()] = $this->getCategoryId($rawCategory->getUId(), $context);
        }

        foreach ($rawCategories->getElements() as $rawCategory) {
            $elementId  = Uuid::randomHex();
            $categoryId = $this->getCategoryId($rawCategory->getUId(), $context);

            $this->runService->createNewElement(
                $elementId,
                $rawCategory->getName(),
                'category',
                $context
            );

            $isSuccess = true;

            $notificationData['categoryId']       = $rawCategory->getId();
            $notificationData['parentCategoryId'] = $rawCategory->getParentId();

            $updateKey = md5(
                CategoryDefinition::ENTITY_NAME .
                $categoryId .
                $catalogMetadata->getLanguageCode()
            );

            $isUpdated = array_key_exists($updateKey, $this->updatedCategoryIds);

            $notificationData['skipped'] = $isUpdated;

            if ($isUpdated) {
                try {
                    $parentId = $rootCategoryId;

                    if ($rawCategory->getParentId() !== null) {
                        $parentId = $categoryIdSw6IdMapping[$rawCategory->getParentId()];
                    }

                    $categoryMediaPath = $rawCategory->getMediaPaths()['Web Kataloggruppen Hauptbild'] ?? [];

                    $mediaId = null;

                    if (!empty($categoryMediaPath) && array_key_exists('Web Kataloggruppen Hauptbild', $rawCategory->getMediaPaths())) {
                        try {
                            $mediaId = $this->mediaHelper->getMediaIdByPath($categoryMediaPath, CategoryDefinition::ENTITY_NAME, $context);
                        } catch (\Throwable $exception) {
                            $this->notificationHelper->addNotification(
                                $exception->getMessage(),
                                'category_import',
                                $notificationData,
                                $catalogMetadata
                            );
                        }
                    }

                    $categoryData = [
                        'id'                     => $categoryIdSw6IdMapping[$rawCategory->getId()],
                        'swagDynamicAccessRules' => $this->getDynamicAccessRules($sortimentId, $context),
                        'parentId'               => $parentId,
                        'active'                 => true,
                        'cmsPageId'              => $parentId === $rootCategoryId
                            ? $this->configService->getString(Configuration::CONFIG_KEY_CATEGORY_MAIN_CMS_PAGE)
                            : $this->configService->getString(Configuration::CONFIG_KEY_CATEGORY_NORMAL_CMS_PAGE),
                        CategoryExtension::EXTENSION_NAME => [
                            'catalogId' => $catalogId,
                            'uId'       => $rawCategory->getUId(),
                        ],
                        'translations' => [
                            $catalogMetadata->getLanguageCode() => [
                                'name'                                       => $rawCategory->getName(),
                                ReiffTheme::THEME_CUSTOM_FIELD_CATEGORY_ICON => $mediaId,
                            ],
                        ],
                    ];

                    $this->entitySyncer->addOperation(
                        CategoryDefinition::ENTITY_NAME,
                        SyncOperation::ACTION_UPSERT,
                        $categoryData
                    );

                    $this->entitySyncer->flush($context);

                    $this->updatedCategoryIds[$updateKey] = true;
                } catch (\Throwable $exception) {
                    $isSuccess = false;
                    $runStatus = false;

                    $this->notificationHelper->addNotification(
                        $exception->getMessage(),
                        'category_import',
                        $notificationData,
                        $catalogMetadata
                    );
                }
            }

            $this->runService->markAsHandled(
                $elementId,
                $isSuccess,
                $notificationData,
                $catalogMetadata->getArchivedFilename(),
                $context
            );
        }

        $this->runService->finalizeRun($runStatus, $catalogMetadata->getArchivedFilename(), $context);
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
