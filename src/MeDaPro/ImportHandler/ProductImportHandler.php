<?php

declare(strict_types=1);

namespace ReiffIntegrations\MeDaPro\ImportHandler;

use Doctrine\DBAL\Connection;
use K10rIntegrationHelper\MappingSystem\MappingService;
use K10rIntegrationHelper\Observability\RunService;
use ReiffIntegrations\Installer\CustomFieldInstaller;
use ReiffIntegrations\MeDaPro\DataAbstractionLayer\CategoryExtension;
use ReiffIntegrations\MeDaPro\DataProvider\RuleProvider;
use ReiffIntegrations\MeDaPro\Helper\MediaHelper;
use ReiffIntegrations\MeDaPro\Helper\NotificationHelper;
use ReiffIntegrations\MeDaPro\Importer\ManufacturerImporter;
use ReiffIntegrations\MeDaPro\Message\ProductImportMessage;
use ReiffIntegrations\MeDaPro\Struct\CatalogMetadata;
use ReiffIntegrations\MeDaPro\Struct\ProductCollection;
use ReiffIntegrations\MeDaPro\Struct\ProductStruct;
use ReiffIntegrations\Util\EntitySyncer;
use Shopware\Core\Content\Product\Aggregate\ProductConfiguratorSetting\ProductConfiguratorSettingDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductCrossSelling\ProductCrossSellingDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\DataAbstractionLayer\ProductIndexingMessage;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Sync\SyncOperation;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexer;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Unit\UnitDefinition;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler(fromTransport: 'import')]
class ProductImportHandler
{
    public const DEFAULT_CONTENT_QUANTITY = 1;

    public const PRODUCT_MEDIA_FIELDS = [
        'Web Groß Hauptbild',
        'Web Groß Detailbild 1',
        'Web Groß Detailbild 2',
        'Web Mittel Hauptbild',
        'Web Mittel Detailbild 1',
        'Web Mittel Detailbild 2',
        'Web Klein Hauptbild',
        'Web Klein Detailbild 1',
        'Web Klein Detailbild 2',
        'Web Logo 2', // Alternatives Herstellerbild
        'Gefahrstoffsymbol GHS01',
        'Gefahrstoffsymbol GHS02',
        'Gefahrstoffsymbol GHS03',
        'Gefahrstoffsymbol GHS04',
        'Gefahrstoffsymbol GHS05',
        'Gefahrstoffsymbol GHS06',
        'Gefahrstoffsymbol GHS07',
        'Gefahrstoffsymbol GHS08',
        'Gefahrstoffsymbol GHS09',
        'Web Piktogramm allg 1',
        'Web Piktogramm allg 2',
        'Web Piktogramm allg 3',
        'Web Piktogramm allg 4',
        'Web Piktogramm allg 5',
        'Web Piktogramm allg 6',
        'Web Piktogramm allg 7',
        'Technisches Datenblatt',
        'Sicherheitsdatenblatt DE',
        'Sicherheitsdatenblatt',
        'Sicherheitsdatenblatt EN',
        'Montageanleitung',
    ];

    private const VISIBILITY_ID_PREFIX   = ProductVisibilityDefinition::ENTITY_NAME;
    private const CONFIGURATOR_ID_PREFIX = ProductConfiguratorSettingDefinition::ENTITY_NAME;
    private const CROSSSELLING_ID_PREFIX = ProductCrossSellingDefinition::ENTITY_NAME;
    private const NEGATIVE_BOOL_VALUE    = 'nein';
    private const POSITIVE_ANGEBOT_VALUE = 'angebot';
    private const POSITIVE_ANFRAGE_VALUE = '1';
    private const POSITIVE_NEUHEIT_VALUE = 'neuheit';
    private const POSITIVE_BOOL_VALUE    = 'ja';

    private ?string $taxId          = null;
    private ?string $salesChannelId = null;
    /** @var null[]|string[] */
    private array $categoryIds = [];
    /** @var ProductIndexingMessage[] */
    private array $indexingMessages = [];
    /** @var string[] */
    private array $productIdsByNumber = [];

    public function __construct(
        private readonly EntitySyncer $entitySyncer,
        private readonly Connection $connection,
        private readonly EntityRepository $productRepository,
        private readonly EntityRepository $taxRepository,
        private readonly EntityRepository $salesChannelRepository,
        private readonly EntityRepository $categoryRepository,
        private readonly MediaHelper $mediaHelper,
        private readonly MessageBusInterface $messageBus,
        private readonly EntityIndexer $productIndexer,
        private readonly RuleProvider $ruleProvider,
        private readonly RunService $runService,
        private readonly NotificationHelper $notificationHelper,
        private readonly MappingService $mappingService,
    ) {
    }

    public function __invoke(ProductImportMessage $message): void
    {
        $this->handle($message);
    }

    public function handle(ProductImportMessage $message): void
    {
        $context = $message->getContext();
        $context->addState(EntityIndexerRegistry::USE_INDEXING_QUEUE);

        $productStruct   = $message->getProduct();
        $catalogMetadata = $message->getCatalogMetadata();

        $notificationData = [
            'catalogId'        => $catalogMetadata->getCatalogId(),
            'sortimentId'      => $catalogMetadata->getSortimentId(),
            'language'         => $catalogMetadata->getLanguageCode(),
            'archivedFilename' => $catalogMetadata->getArchivedFileName(),
            'productNumber'    => $message->getProduct()->getProductNumber(),
        ];

        $isSuccess = true;

        try {
            $this->runService->restartRunContext($context, $message->getElementId());

            $this->importProduct(
                $productStruct,
                $catalogMetadata,
                $context
            );

            $this->finalizeProduct($context);
        } catch (\Throwable $exception) {
            $notificationData['exception'] = $exception->getMessage();

            $this->notificationHelper->addNotification(
                'Product import failed',
                'product_import',
                $notificationData,
                $catalogMetadata
            );

            $this->notificationHelper->handleAsync($context);

            $isSuccess = false;
        }

        $this->runService->markAsHandled(
            $message->getElementId(),
            $isSuccess,
            $notificationData,
            $catalogMetadata->getArchivedFilename(),
            $context
        );
    }

    private function getProductIdForNumber(string $productNumber, Context $context): ?string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('productNumber', $productNumber));

        return $this->productRepository->searchIds($criteria, $context)->firstId();
    }

    private function getTaxId(Context $context): string
    {
        if (!$this->taxId) {
            $this->taxId = $this->taxRepository->searchIds(new Criteria(), $context)->firstId();
        }

        return (string) $this->taxId;
    }

    private function getProductPrice(ProductStruct $productStruct): array
    {
        /** @var string $priceString */
        $priceString = $productStruct->getDataByKey('Preis 1');

        $price = (float) str_replace(',', '.', $priceString);

        // Calculate correct unit price for product based on price's base quantity and product content quantity
        // e.g. price is based on 100 pieces, unit contains 20 pieces -> price / 100 * 20
        $priceQuantity   = (int) $productStruct->getDataByKey('Preismenge');
        $minQuantity     = $productStruct->getDataByKey('Mindestbestellmenge') ?? '';
        $contentQuantity = (float) (str_replace(',', '.', $minQuantity) ?: self::DEFAULT_CONTENT_QUANTITY);

        $price = $price / $priceQuantity;

        $gross = $price * 1.19;
        $net   = $price;

        return [
            [
                'currencyId' => Defaults::CURRENCY,
                'gross'      => $gross,
                'net'        => $net,
                'linked'     => true,
            ],
        ];
    }

    private function getProductTranslations(ProductStruct $productStruct, CatalogMetadata $catalogMetadata, Context $context): array
    {
        /** @var string[] $keywords */
        $keywords = array_filter([
            $productStruct->getDataByKey('Schlagwort 1'),
            $productStruct->getDataByKey('Schlagwort 2'),
            $productStruct->getDataByKey('Schlagwort 3'),
            $productStruct->getDataByKey('Schlagwort 4'),
            $productStruct->getDataByKey('Schlagwort 5'),
            $productStruct->getDataByKey('Schlagwort 6'),
            $productStruct->getDataByKey('Schlagwort 7'),
            $productStruct->getDataByKey('Schlagwort 8'),
            $productStruct->getDataByKey('Schlagwort 9'),
        ]);

        $scaleUnit = $productStruct->getDataByKey('Bestelleinheit');

        if (!is_string($scaleUnit)) {
            throw new \RuntimeException(sprintf('Product %s has no unit', $productStruct->getProductNumber()));
        }

        $packUnit = $this->mappingService->fetchTargetMapping(
            sprintf('%s-%s', $catalogMetadata->getLanguageCode(), $scaleUnit),
            'string',
            'string',
            'MeDaPro Bestelleinheit zu mehrsprachiger Shopware Verpackungseinheit',
            $context
        );

        return [
            $catalogMetadata->getLanguageCode() => [
                'name'         => $productStruct->getDataByKey('Bezeichnung'),
                'description'  => $productStruct->getDataByKey('Beschreibung'),
                'keywords'     => implode('; ', $keywords),
                'packUnit'     => $packUnit,
                'customFields' => [
                    CustomFieldInstaller::PRODUCT_ECLASS51               => $productStruct->getDataByKey('ECLASS 51'),
                    CustomFieldInstaller::PRODUCT_ECLASS71               => $productStruct->getDataByKey('ECLASS 71'),
                    CustomFieldInstaller::PRODUCT_MATERIALFRACHTGRUPPE   => $productStruct->getDataByKey('Materialfrachtgruppe SAP'),
                    CustomFieldInstaller::PRODUCT_ANFRAGE                => $productStruct->getDataByKey('Anfrage') === self::POSITIVE_BOOL_VALUE,
                    CustomFieldInstaller::PRODUCT_BANNER_OFFER           => $productStruct->getDataByKey('Banner_Angebot') === self::POSITIVE_ANGEBOT_VALUE,
                    CustomFieldInstaller::PRODUCT_BANNER_NEW             => $productStruct->getDataByKey('Banner_Neuheit') === self::POSITIVE_NEUHEIT_VALUE,
                    CustomFieldInstaller::PRODUCT_ABSCHNITT              => $productStruct->getDataByKey('Abschnitt') === self::POSITIVE_BOOL_VALUE,
                    CustomFieldInstaller::PRODUCT_BUTTON_CAD             => $productStruct->getDataByKey('Button CAD') !== null && $productStruct->getDataByKey('Button CAD') !== self::NEGATIVE_BOOL_VALUE,
                    CustomFieldInstaller::PRODUCT_BUTTON_ZUSCHNITT       => $productStruct->getDataByKey('Artikel konfigurieren') === self::POSITIVE_BOOL_VALUE,
                    CustomFieldInstaller::PRODUCT_VIDEO                  => $productStruct->getDataByKey('Video'),
                    CustomFieldInstaller::PRODUCT_SHIPPING_TIME          => (int) $productStruct->getDataByKey('TradePro Lieferzeit'),
                    CustomFieldInstaller::PRODUCT_PRICE_BASE_QUANTITY    => (int) $productStruct->getDataByKey('Preismenge'),
                    CustomFieldInstaller::PRODUCT_MANUFACTURER_NAME_LOGO => $productStruct->getDataByKey('Logo-Zuordnung'),
                ],
            ],
        ];
    }

    private function getSalesChannelId(Context $context): ?string
    {
        if (!$this->salesChannelId) {
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('typeId', Defaults::SALES_CHANNEL_TYPE_STOREFRONT));

            $this->salesChannelId = $this->salesChannelRepository->searchIds($criteria, $context)->firstId();
        }

        return $this->salesChannelId;
    }

    private function getCategoryByUid(string $uId, Context $context): string
    {
        if (!array_key_exists($uId, $this->categoryIds)) {
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter(sprintf('%s.uId', CategoryExtension::EXTENSION_NAME), $uId));

            $this->categoryIds[$uId] = $this->categoryRepository->searchIds($criteria, $context)->firstId();
        }

        if (!$this->categoryIds[$uId]) {
            throw new \RuntimeException(sprintf('Category %s is missing', $uId));
        }

        return $this->categoryIds[$uId];
    }

    private function getBaseProductData(
        ProductStruct $productStruct,
        CatalogMetadata $catalogMetadata,
        Context $context
    ): array {
        $sortimentId  = $productStruct->getSortimentId();
        $isCloseout   = $this->getIsCloseout($productStruct);
        $isNewArticle = false;
        $productId    = $this->getProductIdForNumber($productStruct->getProductNumber(), $context);

        if (!$productId) {
            $isNewArticle = true;
            $productId    = Uuid::randomHex();
        }

        $data = [
            'id'                     => $productId,
            'productNumber'          => $productStruct->getProductNumber(),
            'taxId'                  => $this->getTaxId($context),
            'price'                  => $this->getProductPrice($productStruct),
            'isCloseout'             => $isCloseout,
            'active'                 => true,
            'properties'             => [],
            'options'                => [],
            'translations'           => $this->getProductTranslations($productStruct, $catalogMetadata, $context),
            'swagDynamicAccessRules' => $this->getDynamicAccessRules($sortimentId, $context),
        ];

        if (!$isCloseout || $isNewArticle) {
            $data['stock'] = 0;
        }

        if (!$sortimentId) {
            $data['reiffProduct'] = [
                'inDefaultSortiment' => true,
            ];
        }

        $manufacturerField = $this->mappingService->fetchTargetMapping(
            sprintf('%s_manufacturer_property_field', $catalogMetadata->getLanguageCode()),
            'string',
            'string',
            'Fieldname of the Property Manufacturer Name',
            $context
        );

        $manufacturerName = $productStruct->getDataByKey($manufacturerField);

        if (!empty($manufacturerName) && is_string($manufacturerName)) {
            if (!$this->manufacturerExists($manufacturerName)) {
                throw new \RuntimeException(sprintf('Manufacturer %s is missing', $manufacturerName));
            }

            $data['manufacturerId'] = ManufacturerImporter::generateManufacturerIdentity($manufacturerName);
        }

        return $data;
    }

    private function getMainProductData(ProductStruct $productStruct, CatalogMetadata $catalogMetadata, Context $context): array
    {
        /** @var string $categoryUid */
        $categoryUid = $productStruct->getDataByKey('category');

        $baseProduct = $this->getBaseProductData($productStruct, $catalogMetadata, $context);

        return array_merge(
            $baseProduct,
            [
                'visibilities' => [
                    [
                        'id'             => md5(sprintf('%s-%s', self::VISIBILITY_ID_PREFIX, $productStruct->getProductNumber())),
                        'salesChannelId' => $this->getSalesChannelId($context),
                        'visibility'     => ProductVisibilityDefinition::VISIBILITY_ALL,
                    ],
                ],
                'categories' => [
                    ['id' => $this->getCategoryByUid($categoryUid, $context)],
                ],
                'configuratorSettings' => $this->getRemainingConfiguratorOptions($baseProduct['id'], $productStruct->getVariants(), $catalogMetadata, $context),
            ]
        );
    }

    /**
     * Checks if product has switched main products, reindexes the old product
     */
    private function handleMainProductChange(string $newMainProductId, string $variantId, Context $context): void
    {
        $criteria = new Criteria();
        $criteria
            ->addFilter(new NotFilter(MultiFilter::CONNECTION_AND, [new EqualsFilter('id', $newMainProductId)]))
            ->addFilter(new EqualsFilter('parentId', null))
            ->addFilter(new EqualsFilter('children.id', $variantId));
        $oldMainProductId = $this->productRepository->searchIds($criteria, $context)->firstId();

        if ($oldMainProductId) {
            $indexingMessage = new ProductIndexingMessage($oldMainProductId, null, $context);
            $indexingMessage->setIndexer($this->productIndexer->getName());
            $this->indexingMessages[$oldMainProductId] = $indexingMessage;
        }
    }

    private function getCrossSellings(ProductStruct $productStruct, CatalogMetadata $catalogMetadata): array
    {
        $crossSellings = [];
        $position      = null;

        foreach ($productStruct->getCrossSellingGroups() as $group => $productNumbers) {
            $productIds = $this->getProductIdsByNumbers($productNumbers);

            if ($position === null) {
                $position = 0;
            } else {
                ++$position;
            }

            if (empty($productIds)) {
                // Products not yet in system, try again next run
                continue;
            }

            $id = md5(sprintf(
                '%s-%s-%s',
                self::CROSSSELLING_ID_PREFIX,
                $productStruct->getProductNumber(),
                $position
            ));

            $crossSelling = [
                'id'           => $id,
                'type'         => ProductCrossSellingDefinition::TYPE_PRODUCT_LIST,
                'active'       => true,
                'position'     => $position,
                'translations' => [
                    $catalogMetadata->getLanguageCode() => [
                        'name' => $group,
                    ],
                ],
            ];

            if ($catalogMetadata->isSystemLanguage()) {
                foreach (array_values($productIds) as $productPosition => $productId) {
                    $crossSelling['assignedProducts'][] = [
                        'productId' => $productId,
                        'position'  => $productPosition,
                    ];
                }
            }

            $crossSellings[] = $crossSelling;
        }

        return $crossSellings;
    }

    private function getProductIdsByNumbers(array $productNumbers): array
    {
        $missingNumbers = array_diff($productNumbers, array_keys($this->productIdsByNumber));

        if (count($missingNumbers) > 0) {
            $products = $this->connection->fetchAllAssociative('SELECT LOWER(HEX(id)) AS id, product_number FROM product WHERE product_number IN (:productNumbers)', ['productNumbers' => $missingNumbers], ['productNumbers' => Connection::PARAM_STR_ARRAY]);

            /** @var string[] $product */
            foreach ($products as $product) {
                $this->productIdsByNumber[$product['product_number']] = $product['id'];
            }
        }

        return array_intersect_key($this->productIdsByNumber, array_combine($productNumbers, $productNumbers));
    }

    private function isInDefaultSortiment(array $product): bool
    {
        return (bool) $this->connection->fetchOne('SELECT 1 FROM `reiff_product` WHERE product_id = UNHEX(:id) AND product_version_id = UNHEX(:versionId) AND in_default_sortiment = 1', ['id' => $product['id'], 'versionId' => Defaults::LIVE_VERSION]);
    }

    /**
     * @param string[] $configuratorSettingIds
     */
    private function cleanupMainProduct(string $productId, array $configuratorSettingIds): void
    {
        $this->connection->executeStatement('DELETE FROM product_configurator_setting WHERE product_id = :productId AND id NOT IN (:configuratorSettingIds)', [
            'productId'              => Uuid::fromHexToBytes($productId),
            'configuratorSettingIds' => Uuid::fromHexToBytesList($configuratorSettingIds),
        ], [
            'configuratorSettingIds' => Connection::PARAM_STR_ARRAY,
        ]);
    }

    private function cleanupProduct(string $productId): void
    {
        $this->connection->executeStatement('DELETE FROM product_property WHERE product_id = :productId', ['productId' => Uuid::fromHexToBytes($productId)]);
        $this->connection->executeStatement('DELETE FROM product_option WHERE product_id = :productId', ['productId' => Uuid::fromHexToBytes($productId)]);
        $this->connection->executeStatement('DELETE FROM product_cross_selling WHERE product_id = :productId', ['productId' => Uuid::fromHexToBytes($productId)]);
        $this->connection->executeStatement('DELETE FROM product_media WHERE product_id = :productId', ['productId' => Uuid::fromHexToBytes($productId)]);
    }

    private function addProperties(array &$product, ProductStruct $productStruct): void
    {
        $properties = $productStruct->getDataByKey('properties');

        if (!is_array($properties)) {
            return;
        }

        foreach ($properties as $property) {
            $product['properties'][] = [
                'id' => $property['optionId'],
            ];
        }
    }

    private function addOptions(array &$mainProduct, array &$variant, ProductStruct $productStruct): void
    {
        $options = $productStruct->getDataByKey('options');

        if (!is_array($options)) {
            return;
        }

        foreach ($options as $property) {
            $variant['options'][] = [
                'id' => $property['optionId'],
            ];

            $mainProduct['configuratorSettings'][] = [
                'id'       => md5(sprintf('%s-%s-%s', self::CONFIGURATOR_ID_PREFIX, $mainProduct['id'], $property['optionId'])),
                'optionId' => $property['optionId'],
            ];
        }
    }

    /**
     * @see self::PRODUCT_MEDIA_FIELDS Keep the constant in sync with any field changes you make here, otherwise there may be conflicts during import.
     */
    private function addMedia(
        array &$variant,
        ProductStruct $productStruct,
        Context $context,
        CatalogMetadata $catalogMetadata
    ): void {
        /** @var string[] $mediaPaths */
        $mediaPaths = [
            $productStruct->getDataByKey('Web Groß Hauptbild'),
            $productStruct->getDataByKey('Web Groß Detailbild 1'),
            $productStruct->getDataByKey('Web Groß Detailbild 2'),
            $productStruct->getDataByKey('Web Mittel Hauptbild'),
            $productStruct->getDataByKey('Web Mittel Detailbild 1'),
            $productStruct->getDataByKey('Web Mittel Detailbild 2'),
            $productStruct->getDataByKey('Web Klein Hauptbild'),
            $productStruct->getDataByKey('Web Klein Detailbild 1'),
            $productStruct->getDataByKey('Web Klein Detailbild 2'),
            $productStruct->getDataByKey('Web Logo 2'), // Alternatives Herstellerbild
        ];
        $mediaPaths = array_unique(array_filter($mediaPaths));

        $coverPath = (string) array_shift($mediaPaths);

        if (!empty($coverPath)) {
            try {
                $mediaId = $this->mediaHelper->getMediaIdByPath($coverPath, ProductDefinition::ENTITY_NAME, $context);

                if ($mediaId) {
                    $variant['cover'] = [
                        'mediaId' => $mediaId,
                    ];
                } else {
                    throw new \RuntimeException(sprintf('could not find product media at the location: %s', $coverPath));
                }
            } catch (\Throwable $exception) {
                // fail silently as the media file was already reported during the media import
            }
        }

        $mediaFiles = [
            [
                'files'        => $mediaPaths,
                'customFields' => [],
            ],
            [
                'files' => array_unique(
                    array_filter([
                        $productStruct->getDataByKey('Gefahrstoffsymbol GHS01'),
                        $productStruct->getDataByKey('Gefahrstoffsymbol GHS02'),
                        $productStruct->getDataByKey('Gefahrstoffsymbol GHS03'),
                        $productStruct->getDataByKey('Gefahrstoffsymbol GHS04'),
                        $productStruct->getDataByKey('Gefahrstoffsymbol GHS05'),
                        $productStruct->getDataByKey('Gefahrstoffsymbol GHS06'),
                        $productStruct->getDataByKey('Gefahrstoffsymbol GHS07'),
                        $productStruct->getDataByKey('Gefahrstoffsymbol GHS08'),
                        $productStruct->getDataByKey('Gefahrstoffsymbol GHS09'),
                    ])
                ),
                'customFields' => [CustomFieldInstaller::MEDIA_GEFAHRSTOFF => true],
            ],
            [
                'files' => array_unique(
                    array_filter([
                        $productStruct->getDataByKey('Web Piktogramm allg 1'),
                        $productStruct->getDataByKey('Web Piktogramm allg 2'),
                        $productStruct->getDataByKey('Web Piktogramm allg 3'),
                        $productStruct->getDataByKey('Web Piktogramm allg 4'),
                        $productStruct->getDataByKey('Web Piktogramm allg 5'),
                        $productStruct->getDataByKey('Web Piktogramm allg 6'),
                        $productStruct->getDataByKey('Web Piktogramm allg 7'),
                    ])
                ),
                'customFields' => [CustomFieldInstaller::MEDIA_PICTOGRAM => true],
            ],
            [
                'files' => array_unique(
                    array_filter([
                        $productStruct->getDataByKey('Technisches Datenblatt'),
                        $productStruct->getDataByKey('Sicherheitsdatenblatt DE') ?? $productStruct->getDataByKey('Sicherheitsdatenblatt'),
                        $productStruct->getDataByKey('Sicherheitsdatenblatt EN'),
                        $productStruct->getDataByKey('Montageanleitung'),
                    ])
                ),
                'customFields' => [CustomFieldInstaller::MEDIA_DOWNLOAD => true],
            ],
        ];

        foreach ($mediaFiles as $mediaFile) {
            foreach ($mediaFile['files'] as $mediaPath) {
                try {
                    $mediaId = $this->mediaHelper->getMediaIdByPath($mediaPath, ProductDefinition::ENTITY_NAME, $context);

                    if ($mediaId) {
                        $variant['media'][] = [
                            'customFields' => $mediaFile['customFields'],
                            'media'        => [
                                'id'           => $mediaId,
                                'translations' => [
                                    $catalogMetadata->getLanguageCode() => [
                                        'customFields' => $mediaFile['customFields'],
                                    ],
                                ],
                            ],
                        ];
                    } else {
                        throw new \RuntimeException(sprintf('could not find product media at the location: %s', $mediaPath));
                    }
                } catch (\Throwable $exception) {
                    // fail silently as the media file was already reported during the media import
                }
            }
        }
    }

    private function addUnits(array &$product, ProductStruct $productStruct, Context $context): void
    {
        $packagingUnit = $productStruct->getDataByKey('Verpackungsmenge');

        if ($packagingUnit !== null && $packagingUnit !== '') {
            $product['purchaseUnit'] = $packagingUnit;
        }

        $scaleUnit = $productStruct->getDataByKey('Bestelleinheit');

        if (!is_string($scaleUnit)) {
            throw new \RuntimeException(sprintf('Product %s has no unit', $productStruct->getProductNumber()));
        }

        $product['unitId'] = $this->mappingService->fetchTargetMapping(
            $scaleUnit,
            'string',
            UnitDefinition::ENTITY_NAME,
            'MeDaPro Bestelleinheit zu Shopware Maßeinheiten',
            $context
        );
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

    private function finalizeProduct(Context $context): void
    {
        $this->entitySyncer->flush($context);

        foreach ($this->indexingMessages as $indexingMessage) {
            $this->messageBus->dispatch($indexingMessage);
        }

        $this->indexingMessages = [];
    }

    private function getRemainingConfiguratorOptions(string $mainProductId, ProductCollection $variants, CatalogMetadata $catalogMetadata, Context $context): array
    {
        $configuratorSettings = $this->connection->fetchAllAssociative(
            '
            SELECT pcs.id AS id, pcs.property_group_option_id AS optionId FROM product_configurator_setting pcs
                INNER JOIN product child ON child.parent_id = pcs.product_id
                INNER JOIN product_option po ON po.property_group_option_id = pcs.property_group_option_id AND po.product_id = child.id
                WHERE pcs.product_id = :productId AND child.id NOT IN (:childIds) AND pcs.product_version_id = :versionId AND po.product_version_id = :versionId',
            [
                'versionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
                'productId' => Uuid::fromHexToBytes($mainProductId),
                'childIds'  => Uuid::fromHexToBytesList(array_column(array_map(function (ProductStruct $variantStruct) use ($catalogMetadata, $context) {
                    return $this->getBaseProductData($variantStruct, $catalogMetadata, $context);
                }, $variants->getElements()), 'id')),
            ],
            [
                'childIds' => Connection::PARAM_STR_ARRAY,
            ]
        );

        return array_map(
            static function (array $configuratorSetting) use ($mainProductId) {
                /** @var string $optionId */
                $optionId = $configuratorSetting['optionId'];

                return [
                    'id'       => md5(sprintf('%s-%s-%s', self::CONFIGURATOR_ID_PREFIX, $mainProductId, Uuid::fromBytesToHex($optionId))),
                    'optionId' => Uuid::fromBytesToHex($optionId),
                ];
            },
            $configuratorSettings
        );
    }

    private function getIsCloseout(ProductStruct $productStruct): bool
    {
        return (bool) $productStruct->getDataByKey('Lagerverkauf');
    }

    private function importProduct(
        ProductStruct $productStruct,
        CatalogMetadata $catalogMetadata,
        Context $context,
    ): void {
        $mainProduct = $this->getMainProductData($productStruct, $catalogMetadata, $context);

        if ($productStruct->getSortimentId()) {
            $allVariantsInDefaultSortiment = true;
            $variants                      = [];
            foreach ($productStruct->getVariants() as $variantStruct) {
                $variant = $this->getBaseProductData($variantStruct, $catalogMetadata, $context);

                if (!$this->isInDefaultSortiment($variant)) {
                    $allVariantsInDefaultSortiment = false;

                    break;
                }

                $variants[] = $variant;
            }

            if ($allVariantsInDefaultSortiment && $this->isInDefaultSortiment($mainProduct)) {
                // Skip further processing, just persist dynamic access rules, products were created via the default sortiment already
                foreach (array_column($mainProduct['swagDynamicAccessRules'], 'id') as $ruleId) {
                    $this->connection->executeQuery('REPLACE INTO `swag_dynamic_access_product_rule` (`product_id`, `product_version_id`, `rule_id`) VALUES(:productId, :versionId, :ruleId)', [
                        'productId' => Uuid::fromHexToBytes($mainProduct['id']),
                        'versionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
                        'ruleId'    => Uuid::fromHexToBytes($ruleId),
                    ]);
                }

                foreach ($variants as $variant) {
                    foreach (array_column($variant['swagDynamicAccessRules'], 'id') as $ruleId) {
                        $this->connection->executeQuery('REPLACE INTO `swag_dynamic_access_product_rule` (`product_id`, `product_version_id`, `rule_id`) VALUES(:productId, :versionId, :ruleId)', [
                            'productId' => Uuid::fromHexToBytes($variant['id']),
                            'versionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
                            'ruleId'    => Uuid::fromHexToBytes($ruleId),
                        ]);
                    }
                }

                $this->finalizeProduct($context);

                return;
            }
        }

        /** @var string $mainCover */
        $mainCover = $productStruct->getDataByKey('Web Groß Hauptbild');

        if (!empty($mainCover) && $catalogMetadata->isSystemLanguage()) {
            try {
                $mainCoverMediaId = $this->mediaHelper->getMediaIdByPath($mainCover, ProductDefinition::ENTITY_NAME, $context);

                if ($mainCoverMediaId) {
                    $mainProduct['cover'] = [
                        'mediaId' => $mainCoverMediaId,
                    ];
                } else {
                    throw new \RuntimeException(sprintf('could not find product media at the location: %s', $mainCover));
                }
            } catch (\Throwable $exception) {
                // fail silently as the media file was already reported during the media import
            }
        }

        if ($catalogMetadata->isSystemLanguage()) {
            $this->addUnits($mainProduct, $productStruct, $context);

            $this->cleanupMainProduct(
                $mainProduct['id'],
                array_column($mainProduct['configuratorSettings'], 'id')
            );
        }

        $variants = [];

        foreach ($productStruct->getVariants() as $variantStruct) {
            $variant = array_merge(
                $this->getBaseProductData($variantStruct, $catalogMetadata, $context),
                [
                    'parentId' => $mainProduct['id'],
                ]
            );

            if ($catalogMetadata->isSystemLanguage()) {
                $this->cleanupProduct($variant['id']);
                $this->handleMainProductChange($mainProduct['id'], $variant['id'], $context);

                $variant['manufacturerNumber'] = $variantStruct->getDataByKey('Herstellerartikelnummer');
                $variant['ean']                = $variantStruct->getDataByKey('EAN');

                $minPurchase = $variantStruct->getDataByKey('Mindestbestellmenge');

                $variant['minPurchase']   = ((int) $minPurchase > 0) ? (int) $minPurchase : 1;
                $variant['purchaseSteps'] = $variant['minPurchase'];
            }

            $variant['crossSellings'] = $this->getCrossSellings($variantStruct, $catalogMetadata);

            if ($catalogMetadata->isSystemLanguage()) {
                if (is_array($variantStruct->getDataByKey('properties'))) {
                    $this->addProperties($variant, $variantStruct);
                }

                if (is_array($variantStruct->getDataByKey('options'))) {
                    $this->addOptions($mainProduct, $variant, $variantStruct);
                }

                $this->addMedia($variant, $variantStruct, $context, $catalogMetadata);

                $this->addUnits($variant, $variantStruct, $context);
            }

            $variants[] = $variant;
        }

        $this->entitySyncer->addOperation(ProductDefinition::ENTITY_NAME, SyncOperation::ACTION_UPSERT, $mainProduct);
        $this->entitySyncer->addOperations(ProductDefinition::ENTITY_NAME, SyncOperation::ACTION_UPSERT, $variants);
    }

    private function manufacturerExists(string $manufacturerName): bool
    {
        $manufacturer = $this->connection->fetchOne(
            'SELECT LOWER(HEX(id)) AS id FROM product_manufacturer WHERE id = :id',
            [
                'id' => Uuid::fromHexToBytes(
                    ManufacturerImporter::generateManufacturerIdentity($manufacturerName)
                )
            ]
        );

        if (empty($manufacturer)) {
            return false;
        }

        return true;
    }
}
