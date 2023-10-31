<?php

declare(strict_types=1);

namespace ReiffIntegrations\MeDaPro\ImportHandler;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use ReiffIntegrations\MeDaPro\Helper\MediaHelper;
use ReiffIntegrations\MeDaPro\Message\ManufacturerImportMessage;
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
use Shopware\Core\System\SystemConfig\SystemConfigService;

class ManufacturerImportHandler extends AbstractImportHandler
{
    private const BATCH_SIZE            = 100;

    /** @var bool[] */
    private array $updatedManufacturerIds = [];
    private int $manufacturerBatchCount   = 0;

    public function __construct(
        LoggerInterface $logger,
        SystemConfigService $configService,
        Mailer $mailer,
        EntitySyncer $entitySyncer,
        Connection $connection,
        private readonly EntityRepository $manufacturerRepository,
        private readonly MediaHelper $mediaHelper,
    ) {
        parent::__construct($logger, $configService, $mailer, $entitySyncer, $connection);
    }

    public function supports(AbstractImportMessage $message): bool
    {
        return $message instanceof ManufacturerImportMessage;
    }

    /**
     * @param ProductsStruct $struct
     */
    public function getMessage(
        Struct $struct,
        string $archiveFileName,
        CatalogMetadata $catalogMetadata,
        Context $context
    ):  ManufacturerImportMessage
    {
        return new ManufacturerImportMessage($struct, $archiveFileName, $catalogMetadata, $context);
    }

    public function __invoke(AbstractImportMessage $message): void
    {
        $this->handle($message);
    }

    /**
     * @param ManufacturerImportMessage $message
     */
    public function handle(AbstractImportMessage $message): void
    {
        $context   = $message->getContext();
        $catalogMetadata = $message->getCatalogMetadata();

        $products  = $message->getProductsStruct()->getProducts();

        $this->manufacturerBatchCount = 0;

        foreach ($products as $mainProduct) {
            $this->updateManufacturer($mainProduct, $catalogMetadata, $context);

            if ($this->manufacturerBatchCount >= self::BATCH_SIZE) {
                if ($context->hasState(DryRunState::NAME)) {
                    dump($this->entitySyncer->getOperations());

                    $this->entitySyncer->reset();
                }

                $this->entitySyncer->flush($context);
                $this->manufacturerBatchCount = 0;
            }
        }

        if ($this->manufacturerBatchCount > 0) {
            if ($context->hasState(DryRunState::NAME)) {
                dump($this->entitySyncer->getOperations());

                $this->entitySyncer->reset();
            }

            $this->entitySyncer->flush($context);
        }
    }

    protected function getLogIdentifier(): string
    {
        return self::class;
    }

    private function updateManufacturer(
        ProductStruct $mainProductStruct,
        CatalogMetadata $catalogMetadata,
        Context $context
    ): void
    {
        foreach ($mainProductStruct->getVariants() as $productStruct) {
            $manufacturerName = $productStruct->getDataByKey('Hersteller');

            if (empty($manufacturerName) || !is_string($manufacturerName)) {
                continue;
            }

            $updateKey = md5(
                ProductManufacturerDefinition::ENTITY_NAME .
                $manufacturerName.
                $catalogMetadata->getLanguageCode()
            );

            if (array_key_exists($updateKey, $this->updatedManufacturerIds)) {
                continue;
            }

            $manufacturerId = md5(sprintf('%s-%s', ProductManufacturerDefinition::ENTITY_NAME, $manufacturerName));

            $data = [
                'id'      => $manufacturerId,
                'translations' => [
                    $catalogMetadata->getLanguageCode() => [
                        'name' => $manufacturerName,
                    ],
                ],
            ];

            if ($catalogMetadata->isSystemLanguage()) {
                /** @var string $manufacturerImage */
                $manufacturerImage = $productStruct->getDataByKey('Web Logo 1');

                $manufacturerMediaId = null;

                if (!empty($manufacturerImage)) {
                    $manufacturerMediaId = $this->mediaHelper->getMediaIdByPath($manufacturerImage, ProductManufacturerDefinition::ENTITY_NAME, $context);

                    if (!$manufacturerMediaId) {
                        $this->addError(new \RuntimeException(sprintf('could not find media at the location: %s', $manufacturerImage)), $context);
                    }
                }

                $data['mediaId'] = $manufacturerMediaId;
            }

            if ($context->hasState(DryRunState::NAME)) {
                $this->entitySyncer->addOperation(ProductManufacturerDefinition::ENTITY_NAME, SyncOperation::ACTION_UPSERT, $data);
            } else {
                $this->manufacturerRepository->upsert([$data], $context);
            }

            ++$this->manufacturerBatchCount;
            $this->updatedManufacturerIds[$updateKey] = true;
        }
    }
}
