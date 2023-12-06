<?php

declare(strict_types=1);

namespace ReiffIntegrations\MeDaPro\Importer;

use K10rIntegrationHelper\Observability\RunService;
use ReiffIntegrations\MeDaPro\Helper\MediaHelper;
use ReiffIntegrations\MeDaPro\Helper\NotificationHelper;
use ReiffIntegrations\MeDaPro\Struct\CatalogMetadata;
use ReiffIntegrations\MeDaPro\Struct\ProductsStruct;
use ReiffIntegrations\Util\EntitySyncer;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;
use Shopware\Core\Framework\Api\Sync\SyncOperation;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;

class ManufacturerImporter
{
    /** @var bool[] */
    private array $updatedManufacturerIds = [];

    public function __construct(
        private readonly EntitySyncer $entitySyncer,
        private readonly MediaHelper $mediaHelper,
        private readonly RunService $runService,
        private readonly NotificationHelper $notificationHelper,
    ) {
    }

    public function importManufacturers(
        ProductsStruct $productsStruct,
        CatalogMetadata $catalogMetadata,
        Context $context
    ): void {
        $this->runService->createRun(
            sprintf(
                'Manufacturer Import (%s)',
                implode('_', array_filter([
                    $catalogMetadata->getSortimentId(),
                    $catalogMetadata->getCatalogId(),
                    $catalogMetadata->getLanguageCode(),
                ]))
            ),
            'manufacturer_import',
            count($productsStruct->getManufacturers()),
            $context
        );

        $runStatus = true;

        $notificationData = [
            'catalogId'        => $catalogMetadata->getCatalogId(),
            'sortimentId'      => $catalogMetadata->getSortimentId(),
            'language'         => $catalogMetadata->getLanguageCode(),
            'archivedFilename' => $catalogMetadata->getArchivedFilename(),
        ];

        foreach ($productsStruct->getManufacturers() as $manufacturer) {
            $elementId = Uuid::randomHex();
            $isSuccess = true;

            $this->runService->createNewElement(
                $elementId,
                $manufacturer['name'],
                'manufacturer',
                $context
            );

            $updateKey = md5(
                ProductManufacturerDefinition::ENTITY_NAME .
                $manufacturer['name'] .
                $catalogMetadata->getLanguageCode()
            );

            if (!array_key_exists($updateKey, $this->updatedManufacturerIds)) {
                try {
                    $data = [
                        'id'           => self::generateManufacturerIdentity($manufacturer['name']),
                        'translations' => [
                            $catalogMetadata->getLanguageCode() => [
                                'name' => $manufacturer['name'],
                            ],
                        ],
                    ];

                    $manufacturerMediaId = null;

                    if (!empty($manufacturer['media'])) {
                        try {
                            $manufacturerMediaId = $this->mediaHelper->getMediaIdByPath(
                                $manufacturer['media'],
                                ProductManufacturerDefinition::ENTITY_NAME,
                                $context
                            );
                        } catch (\Throwable $exception) {
                            $notificationData['exception'] = $exception->getMessage();

                            $this->notificationHelper->addNotification(
                                'Error during manufacturer media processing',
                                'manufacturer_import',
                                $notificationData,
                                $catalogMetadata
                            );

                            $notificationData['manufacturerImage'] = $manufacturer['media'] ?? null;
                        }
                    }

                    $data['mediaId'] = $manufacturerMediaId;

                    $this->entitySyncer->addOperation(
                        ProductManufacturerDefinition::ENTITY_NAME,
                        SyncOperation::ACTION_UPSERT,
                        $data
                    );

                    $this->entitySyncer->flush($context);

                    $this->updatedManufacturerIds[$updateKey] = true;
                } catch (\Throwable $exception) {
                    $isSuccess = false;
                    $runStatus = false;

                    $this->notificationHelper->addNotification(
                        $exception->getMessage(),
                        'manufacturer_import',
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

    public static function generateManufacturerIdentity(string $manufacturerName): string
    {
        return md5(sprintf('%s-%s', ProductManufacturerDefinition::ENTITY_NAME, $manufacturerName));
    }
}
