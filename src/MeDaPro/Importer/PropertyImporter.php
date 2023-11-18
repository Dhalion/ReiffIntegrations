<?php

declare(strict_types=1);

namespace ReiffIntegrations\MeDaPro\Importer;

use Doctrine\DBAL\Connection;
use K10rIntegrationHelper\NotificationSystem\NotificationService;
use K10rIntegrationHelper\Observability\RunService;
use Psr\Log\LoggerInterface;
use ReiffIntegrations\MeDaPro\Helper\NotificationHelper;
use ReiffIntegrations\MeDaPro\Struct\CatalogMetadata;
use ReiffIntegrations\MeDaPro\Struct\ProductsStruct;
use ReiffIntegrations\Util\Context\DryRunState;
use ReiffIntegrations\Util\EntitySyncer;
use ReiffIntegrations\Util\Handler\AbstractImportHandler;
use ReiffIntegrations\Util\Mailer;
use ReiffIntegrations\Util\Message\AbstractImportMessage;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionDefinition;
use Shopware\Core\Content\Property\PropertyGroupDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Sync\SyncOperation;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class PropertyImporter
{
    private const DISPLAY_TYPE_DROPDOWN          = 'select'; // Shopware has no constant for this yet in PropertyGroupDefinition

    /** @var string[] */
    private array $updatedPropertyGroups = [];

    /** @var bool[] */
    private array $updatedPropertyGroupOptionIds = [];

    public function __construct(
        private readonly EntitySyncer $entitySyncer,
        private readonly RunService $runService,
        private readonly NotificationHelper $notificationHelper,
    ) {}

    public function importProperties(
        string $archivedFileName,
        ProductsStruct $productsStruct,
        CatalogMetadata $catalogMetadata,
        Context $context
    ): void
    {
        $properties = $productsStruct->getProperties();

        $this->runService->createRun(
            sprintf(
                'Property Import (%s - %s)',
                $catalogMetadata->getCatalogId(),
                $catalogMetadata->getLanguageCode()
            ),
            'property_import',
            count($productsStruct->getProperties()),
            $context
        );

        $runStatus = true;

        $notificationData = [
            'catalogId' => $catalogMetadata->getCatalogId(),
            'sortimentId' => $catalogMetadata->getSortimentId(),
            'language' => $catalogMetadata->getLanguageCode(),
            'archivedFilename' => $archivedFileName,
        ];

        foreach ($properties as $property) {
            $elementId = Uuid::randomHex();

            $this->runService->createNewElement(
                $elementId,
                $property['name'],
                'property',
                $context
            );

            $notificationData['property'] = $property;

            try {
                $updateKey = md5(
                    PropertyGroupDefinition::ENTITY_NAME .
                    $property['groupId'] .
                    $catalogMetadata->getLanguageCode()
                );

                if (!array_key_exists($updateKey, $this->updatedPropertyGroups)) {
                    $upsertData = [
                        'id' => $property['groupId'],
                        'displayType'                => self::DISPLAY_TYPE_DROPDOWN,
                        'sortingType'                => PropertyGroupDefinition::SORTING_TYPE_ALPHANUMERIC,
                        'filterable'                 => PropertyGroupDefinition::FILTERABLE,
                        'visibleOnProductDetailPage' => PropertyGroupDefinition::VISIBLE_ON_PRODUCT_DETAIL_PAGE,
                        'translations'               => [
                            $catalogMetadata->getLanguageCode() => [
                                'name'     => $property['name'],
                                'position' => 1,
                            ],
                        ],
                    ];

                    $this->entitySyncer->addOperation(
                        PropertyGroupDefinition::ENTITY_NAME,
                        SyncOperation::ACTION_UPSERT,
                        $upsertData
                    );

                    $this->updatedPropertyGroups[$updateKey] = true;
                }

                foreach ($property['options'] as $optionId => $optionValue) {
                    $updateKey = md5(
                        PropertyGroupOptionDefinition::ENTITY_NAME .
                        $optionId .
                        $catalogMetadata->getLanguageCode()
                    );

                    if (!array_key_exists($updateKey, $this->updatedPropertyGroupOptionIds)) {
                        $upsertData = [
                            'id'           => $optionId,
                            'groupId'      => $property['groupId'],
                            'translations' => [
                                $catalogMetadata->getLanguageCode() => [
                                    'name'     => mb_substr($optionValue, 0, 255),
                                    'position' => 1,
                                ],
                            ],
                        ];

                        $this->entitySyncer->addOperation(
                            PropertyGroupOptionDefinition::ENTITY_NAME,
                            SyncOperation::ACTION_UPSERT,
                            $upsertData
                        );

                        $this->updatedPropertyGroupOptionIds[$updateKey] = true;
                    }
                }

                $this->entitySyncer->flush($context);

                $isSuccess = true;
            } catch (\Throwable $exception) {
                $isSuccess = false;
                $runStatus = false;

                $this->notificationHelper->addNotification(
                    $exception->getMessage(),
                    'property_import',
                    $notificationData,
                    $catalogMetadata
                );
            }

            $this->runService->markAsHandled(
                $elementId,
                $isSuccess,
                $notificationData,
                $archivedFileName,
                $context
            );
        }

        $this->runService->finalizeRun($runStatus, $archivedFileName, $context);
    }
}
