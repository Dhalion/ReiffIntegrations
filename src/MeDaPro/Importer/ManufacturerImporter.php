<?php

declare(strict_types=1);

namespace ReiffIntegrations\MeDaPro\Importer;

use Doctrine\DBAL\Connection;
use K10rIntegrationHelper\Observability\RunService;
use Psr\Log\LoggerInterface;
use ReiffIntegrations\MeDaPro\Helper\IdentityProvider;
use ReiffIntegrations\MeDaPro\Helper\MediaHelper;
use ReiffIntegrations\MeDaPro\Helper\NotificationHelper;
use ReiffIntegrations\MeDaPro\Struct\CatalogMetadata;
use ReiffIntegrations\MeDaPro\Struct\ProductsStruct;
use ReiffIntegrations\MeDaPro\Struct\ProductStruct;
use ReiffIntegrations\Util\Context\DryRunState;
use ReiffIntegrations\Util\EntitySyncer;
use ReiffIntegrations\Util\Handler\AbstractImportHandler;
use ReiffIntegrations\Util\Mailer;
use ReiffIntegrations\Util\Message\AbstractImportMessage;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;
use Shopware\Core\Framework\Api\Sync\SyncOperation;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;

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
        string $archivedFileName,
        ProductsStruct $productsStruct,
        CatalogMetadata $catalogMetadata,
        Context $context
    ): void
    {
        $this->runService->createRun(
            sprintf(
                'Manufacturer Import (%s - %s)',
                $catalogMetadata->getCatalogId(),
                $catalogMetadata->getLanguageCode()
            ),
            'manufacturer_import',
            count($productsStruct->getManufacturers()),
            $context
        );

        $runStatus = true;

        $notificationData = [
            'catalogId' => $catalogMetadata->getCatalogId(),
            'sortimentId' => $catalogMetadata->getSortimentId(),
            'language' => $catalogMetadata->getLanguageCode(),
            'archivedFilename' => $archivedFileName,
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

            $notificationData['manufacturerName'] = $manufacturer['name'];
            $notificationData['manufacturerImage'] = $manufacturer['image'];

            $updateKey = md5(
                ProductManufacturerDefinition::ENTITY_NAME .
                $manufacturer['name'] .
                $catalogMetadata->getLanguageCode()
            );

            if (!array_key_exists($updateKey, $this->updatedManufacturerIds)) {
                try {
                    $data = [
                        'id' => self::generateManufacturerIdentity($manufacturer['name']),
                        'translations' => [
                            $catalogMetadata->getLanguageCode() => [
                                'name' => $manufacturer['name'],
                            ],
                        ],
                    ];

                    $manufacturerMediaId = null;
                    if (!empty($manufacturer['image'])) {
                        try {
                            $manufacturerMediaId = $this->mediaHelper->getMediaIdByPath(
                                $manufacturer['image'],
                                ProductManufacturerDefinition::ENTITY_NAME,
                                $context
                            );

                            if (!$manufacturerMediaId) {
                                throw new \RuntimeException(sprintf('Media not found: %s', $manufacturer['image']));
                            }
                        } catch (\Throwable $exception) {
                            $this->notificationHelper->addNotification(
                                $exception->getMessage(),
                                'manufacturer_import',
                                $notificationData,
                                $catalogMetadata
                            );
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
                $archivedFileName,
                $context
            );
        }

        $this->runService->finalizeRun($runStatus, $archivedFileName, $context);
    }

    public static function generateManufacturerIdentity(string $manufacturerName): string
    {
        return md5(sprintf('%s-%s', ProductManufacturerDefinition::ENTITY_NAME, $manufacturerName));
    }
}
