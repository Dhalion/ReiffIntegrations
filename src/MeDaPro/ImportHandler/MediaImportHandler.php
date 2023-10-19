<?php

declare(strict_types=1);

namespace ReiffIntegrations\MeDaPro\ImportHandler;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use ReiffIntegrations\MeDaPro\Helper\MediaHelper;
use ReiffIntegrations\MeDaPro\Message\MediaImportMessage;
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
use Shopware\Core\System\SystemConfig\SystemConfigService;

class MediaImportHandler extends AbstractImportHandler
{
    private const BATCH_SIZE     = 100;
    private int $mediaBatchCount = 0;

    public function __construct(
        LoggerInterface $logger,
        SystemConfigService $configService,
        Mailer $mailer,
        EntitySyncer $entitySyncer,
        Connection $connection,
        private readonly MediaHelper $mediaHelper,
    ) {
        parent::__construct($logger, $configService, $mailer, $entitySyncer, $connection);
    }

    public function supports(AbstractImportMessage $message): bool
    {
        return $message instanceof MediaImportMessage;
    }

    /**
     * @param ProductsStruct $struct
     */
    public function getMessage(Struct $struct, string $archiveFileName, Context $context): MediaImportMessage
    {
        return new MediaImportMessage($struct, $archiveFileName, $context);
    }

    public function __invoke(AbstractImportMessage $message): void
    {
        $this->handle($message);
    }

    /**
     * @param MediaImportMessage $message
     */
    public function handle(AbstractImportMessage $message): void
    {
        $this->connection->beginTransaction();

        $context               = $message->getContext();
        $this->mediaBatchCount = 0;

        foreach ($message->getProductsStruct()->getProducts() as $mainProduct) {
            $this->updateMediaFromProduct($mainProduct, $context);

            if ($this->mediaBatchCount >= self::BATCH_SIZE) {
                if ($context->hasState(DryRunState::NAME)) {
                    dump($this->entitySyncer->getOperations());
                }

                $this->entitySyncer->flush($context);
                $this->mediaBatchCount = 0;
            }
        }

        if ($this->mediaBatchCount > 0) {
            if ($context->hasState(DryRunState::NAME)) {
                dump($this->entitySyncer->getOperations());
            }

            $this->entitySyncer->flush($context);
        }

        if ($context->hasState(DryRunState::NAME)) {
            $this->connection->rollBack();
        } else {
            $this->connection->commit();
        }
    }

    protected function getLogIdentifier(): string
    {
        return self::class;
    }

    private function updateMediaFromProduct(ProductStruct $mainProductStruct, Context $context): void
    {
        foreach ($mainProductStruct->getVariants() as $productStruct) {
            foreach (ProductImportHandler::PRODUCT_MEDIA_FIELDS as $mediaField) {
                /** @var null|string $media */
                $media = $productStruct->getDataByKey($mediaField);

                if (empty($media)) {
                    continue;
                }

                $mediaId = $this->mediaHelper->getMediaIdByPath($media, ProductDefinition::ENTITY_NAME, $context);

                if (!$mediaId) {
                    $this->addError(new \RuntimeException(sprintf('could not find media at the location: %s', $media)), $context);
                }

                ++$this->mediaBatchCount;
            }

            /** @var string $manufacturerImage */
            $manufacturerImage = $productStruct->getDataByKey('Web Logo 1');

            if (!empty($manufacturerImage)) {
                $manufacturerMediaId = $this->mediaHelper->getMediaIdByPath($manufacturerImage, ProductManufacturerDefinition::ENTITY_NAME, $context);

                if (!$manufacturerMediaId) {
                    $this->addError(new \RuntimeException(sprintf('could not find media at the location: %s', $manufacturerImage)), $context);
                }
            }

            ++$this->mediaBatchCount;
        }
    }
}
