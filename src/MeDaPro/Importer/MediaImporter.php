<?php

declare(strict_types=1);

namespace ReiffIntegrations\MeDaPro\Importer;

use K10rIntegrationHelper\Observability\RunService;
use ReiffIntegrations\MeDaPro\Helper\MediaHelper;
use ReiffIntegrations\MeDaPro\Helper\NotificationHelper;
use ReiffIntegrations\MeDaPro\ImportHandler\ProductImportHandler;
use ReiffIntegrations\MeDaPro\Struct\CatalogMetadata;
use ReiffIntegrations\MeDaPro\Struct\ProductsStruct;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;

class MediaImporter
{
    public function __construct(
        private readonly MediaHelper $mediaHelper,
        private readonly RunService $runService,
        private readonly NotificationHelper $notificationHelper
    ) {
    }

    public function importMedia(
        ProductsStruct $productsStruct,
        CatalogMetadata $catalogMetadata,
        Context $context
    ): void {
        $this->runService->createRun(
            sprintf(
                'Media Import (%s)',
                implode('_', array_filter([
                    $catalogMetadata->getSortimentId(),
                    $catalogMetadata->getCatalogId(),
                    $catalogMetadata->getLanguageCode(),
                ]))
            ),
            'media_import',
            null,
            $context
        );

        $runStatus = true;

        $notificationData = [
            'catalogId'        => $catalogMetadata->getCatalogId(),
            'sortimentId'      => $catalogMetadata->getSortimentId(),
            'language'         => $catalogMetadata->getLanguageCode(),
            'archivedFilename' => $catalogMetadata->getArchivedFilename(),
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

                $hasErrors = false;

                foreach (ProductImportHandler::PRODUCT_MEDIA_FIELDS as $mediaField) {
                    /** @var null|string $media */
                    $media = $productStruct->getDataByKey($mediaField);

                    if (empty($media)) {
                        continue;
                    }

                    try {
                        $this->mediaHelper->getMediaIdByPath($media, ProductDefinition::ENTITY_NAME, $context);
                    } catch (\Throwable $exception) {
                        $isSuccess = false;
                        $runStatus = false;
                        $hasErrors = true;

                        $notificationData['errors'][]      = $exception->getMessage();
                        $notificationData['mediaFields'][] = $mediaField;
                    }
                }

                if ($hasErrors) {
                    $mailData                = $notificationData;
                    $mailData['errors']      = implode("\n", $mailData['errors']);
                    $mailData['mediaFields'] = implode("\n", $mailData['mediaFields']);

                    $this->notificationHelper->addNotification(
                        'media import failed',
                        'media_import',
                        $mailData,
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
}
