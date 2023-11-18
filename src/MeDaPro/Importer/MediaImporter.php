<?php

declare(strict_types=1);

namespace ReiffIntegrations\MeDaPro\Importer;

use Doctrine\DBAL\Connection;
use K10rIntegrationHelper\Observability\RunService;
use Psr\Log\LoggerInterface;
use ReiffIntegrations\MeDaPro\Helper\MediaHelper;
use ReiffIntegrations\MeDaPro\Helper\NotificationHelper;
use ReiffIntegrations\MeDaPro\ImportHandler\ProductImportHandler;
use ReiffIntegrations\MeDaPro\Struct\CatalogMetadata;
use ReiffIntegrations\MeDaPro\Struct\ProductsStruct;
use ReiffIntegrations\MeDaPro\Struct\ProductStruct;
use ReiffIntegrations\Util\Context\DryRunState;
use ReiffIntegrations\Util\EntitySyncer;
use ReiffIntegrations\Util\Handler\AbstractImportHandler;
use ReiffIntegrations\Util\Mailer;
use ReiffIntegrations\Util\Message\AbstractImportMessage;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class MediaImporter
{
    public function __construct(
        private readonly MediaHelper $mediaHelper,
        private readonly RunService $runService,
        private readonly NotificationHelper $notificationHelper
    ) { }

    public function importMedia(
        string $archivedFileName,
        ProductsStruct $productsStruct,
        CatalogMetadata $catalogMetadata,
        Context $context
    ): void
    {
        $this->runService->createRun(
            sprintf(
                'Media Import (%s - %s)',
                $catalogMetadata->getCatalogId(),
                $catalogMetadata->getLanguageCode()
            ),
            'media_import',
            null,
            $context
        );

        $elementCount = 0;
        $runStatus = true;

        $notificationData = [
            'catalogId' => $catalogMetadata->getCatalogId(),
            'sortimentId' => $catalogMetadata->getSortimentId(),
            'language' => $catalogMetadata->getLanguageCode(),
            'archivedFilename' => $archivedFileName,
        ];

        foreach ($productsStruct->getProducts() as $mainProduct) {
            $elementId = Uuid::randomHex();
            $isSuccess = true;

            $this->runService->createNewElement(
                $elementId,
                $mainProduct->getProductNumber(),
                'product_media',
                $context
            );

            foreach ($mainProduct->getVariants() as $productStruct) {
                $notificationData['productNumber'] = $productStruct->getProductNumber();

                foreach (ProductImportHandler::PRODUCT_MEDIA_FIELDS as $mediaField) {
                    $elementCount++;

                    /** @var null|string $media */
                    $media = $productStruct->getDataByKey($mediaField);

                    if (empty($media)) {
                        continue;
                    }

                    try {
                        $mediaId = $this->mediaHelper->getMediaIdByPath($media, ProductDefinition::ENTITY_NAME, $context);

                        if (!$mediaId) {
                            throw new \RuntimeException(sprintf('could not find media at the location: %s', $media));
                        } else {
                            $notificationData['media'][] = $media;
                        }
                    } catch (\Throwable $exception) {
                        $isSuccess = false;
                        $runStatus = false;

                        $this->notificationHelper->addNotification(
                            $exception->getMessage(),
                            'media_import',
                            $notificationData,
                            $catalogMetadata
                        );
                    }
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

        $this->runService->setElementCount($elementCount, $context);
        $this->runService->finalizeRun($runStatus, $archivedFileName, $context);
    }
}
