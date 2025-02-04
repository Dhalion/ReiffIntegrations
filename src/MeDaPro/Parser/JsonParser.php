<?php

declare(strict_types=1);

namespace ReiffIntegrations\MeDaPro\Parser;

use JsonMachine\Items;
use JsonMachine\JsonDecoder\ExtJsonDecoder;
use K10rIntegrationHelper\MappingSystem\MappingService;
use K10rIntegrationHelper\Observability\RunService;
use ReiffIntegrations\MeDaPro\Helper\CrossSellingHelper;
use ReiffIntegrations\MeDaPro\Helper\NotificationHelper;
use ReiffIntegrations\MeDaPro\Struct\CatalogMetadata;
use ReiffIntegrations\MeDaPro\Struct\CatalogStruct;
use ReiffIntegrations\MeDaPro\Struct\CategoryCollection;
use ReiffIntegrations\MeDaPro\Struct\CategoryStruct;
use ReiffIntegrations\MeDaPro\Struct\ProductCollection;
use ReiffIntegrations\MeDaPro\Struct\ProductsStruct;
use ReiffIntegrations\MeDaPro\Struct\ProductStruct;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionDefinition;
use Shopware\Core\Content\Property\PropertyGroupDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;

class JsonParser
{
    private const CATEGORY_TYPE_VARIATION_GROUP = 'variationGroup';
    private const ATTRIBUTE_IDENTIFIER_PROPERTY = 'Textattribute';
    private const ATTRIBUTE_IDENTIFIER_OPTION   = 'Tabellenattribute';
    private const ATTRIBUTE_IDENTIFIER_LABEL    = ' Name';
    private const PRODUCT_FIELD_NUMBER          = 'Artikel-Nr';
    private const CLOSEOUT_FIELD                = 'Lagerverkauf';
    private const CLOSEOUT_IDENTIFIER           = 'Lagerverkauf Name';
    private const ALLOWED_EMPTY_FIELDS          = [ // We have to keep these fields in the structs to know if they are empty strings
        'Button CAD',
    ];

    private static array $propertyMapping  = [];
    private static array $propertyCounters = [];

    public function __construct(
        private readonly RunService $runService,
        private readonly NotificationHelper $notificationHelper,
        private readonly MappingService $mappingService
    ) {
    }

    public function getCategories(
        string $filePath,
        CatalogMetadata $catalogMetadata
    ): CatalogStruct {
        $catalogId   = $catalogMetadata->getCatalogId();
        $sortimentId = $catalogMetadata->getSortimentId();

        $categoryData = $this->getItems($filePath, '/catalogNodes');
        $categories   = [];

        /** @var array $category */
        foreach ($categoryData as $category) {
            if ($category['type'] === self::CATEGORY_TYPE_VARIATION_GROUP) {
                continue;
            }

            $categoryStruct = new CategoryStruct(
                $category['id'],
                $category['parentId'] !== '' ? $category['parentId'] : null,
                $category['uId'],
                $category['type'],
                $category['name'],
                $category['description'],
                $category['depth'],
                $category['media'] ?? []
            );

            $categories[$category['id']] = $categoryStruct;
        }

        return new CatalogStruct($catalogId, new CategoryCollection($categories), basename($filePath), $sortimentId);
    }

    public function getProducts(
        string $filePath,
        CatalogMetadata $catalogMetadata,
        Context $context
    ): ProductsStruct {
        $this->runService->createRun(
            sprintf(
                'Parse Products (%s)',
                implode('_', array_filter([
                    $catalogMetadata->getSortimentId(),
                    $catalogMetadata->getCatalogId(),
                    $catalogMetadata->getLanguageCode(),
                ]))
            ),
            'parse_products',
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
        $notificationErrors = [];

        $rawProducts   = [];
        $products      = [];
        $catalogNodes  = [];
        $properties    = [];
        $manufacturers = [];

        $manufacturerField = $this->mappingService->fetchTargetMapping(
            sprintf('%s_manufacturer_property_field', $catalogMetadata->getLanguageCode()),
            'string',
            'string',
            'Fieldname of the Property Manufacturer Name',
            $context
        );

        foreach ($this->getItems($filePath, '/catalogNodes') as $catalogNode) {
            $catalogNodes[$catalogNode['id']] = $catalogNode;
        }

        foreach ($this->getItems($filePath, '/articles') as $product) {
            $variationGroupId                                                                        = $catalogNodes[$product['variationGroupId']]['uId'];
            $rawProducts[$variationGroupId][$product[self::PRODUCT_FIELD_NUMBER]]['raw']             = $product;
            $rawProducts[$variationGroupId][$product[self::PRODUCT_FIELD_NUMBER]]['raw']['category'] = $catalogNodes[$catalogNodes[$product['variationGroupId']]['parentId']]['uId'];
            $rawProducts[$variationGroupId][$product[self::PRODUCT_FIELD_NUMBER]]['attributes']      = $product;
        }

        $hasErrors = false;

        foreach ($rawProducts as &$variants) {
            $firstVariant = reset($variants);

            if (array_key_exists($manufacturerField, $firstVariant['raw'])) {
                unset($firstVariant['raw'][$manufacturerField]);  // Make sure main product has no manufacturer to prevent inheritance
            }

            if (array_key_exists($manufacturerField, $firstVariant['attributes'])) {
                unset($firstVariant['attributes'][$manufacturerField]);
            }

            if (array_key_exists(self::CLOSEOUT_IDENTIFIER, $firstVariant['raw'])) {
                unset($firstVariant['attributes'][self::CLOSEOUT_IDENTIFIER]);
            }

            if (array_key_exists(self::CLOSEOUT_IDENTIFIER, $firstVariant['attributes'])) {
                unset($firstVariant['attributes'][self::CLOSEOUT_IDENTIFIER]);
            }

            if (array_key_exists(self::CLOSEOUT_FIELD, $firstVariant['attributes'])) {
                unset($firstVariant['attributes'][self::CLOSEOUT_FIELD]);
            }

            if (array_key_exists(self::CLOSEOUT_FIELD, $firstVariant['raw'])) {
                unset($firstVariant['attributes'][self::CLOSEOUT_FIELD]);
            }

            $mainProduct = new ProductStruct(
                $catalogNodes[$firstVariant['raw']['variationGroupId']]['uId'],
                new ProductCollection(),
                $firstVariant['raw'],
                $filePath,
                $catalogMetadata->getSortimentId(),
                $catalogMetadata->getCatalogId()
            );

            $mainProduct->setDataByKey('Bezeichnung', $catalogNodes[$firstVariant['raw']['variationGroupId']]['name']);

            foreach ($variants as $productNumber => &$product) {
                $productNumber = (string) $productNumber;

                $attributeType     = null;
                $attributes        = ['text' => [], 'table' => []];
                $structuredProduct = [];

                foreach ($product['attributes'] as $key => $value) {
                    if (str_starts_with($key, self::ATTRIBUTE_IDENTIFIER_PROPERTY)) {
                        unset($product['attributes'][$key]);
                        $attributeType = 'text';
                    } elseif (str_starts_with($key, self::ATTRIBUTE_IDENTIFIER_OPTION)) {
                        unset($product['attributes'][$key]);
                        $attributeType = 'table';
                    } elseif (str_contains($key, self::ATTRIBUTE_IDENTIFIER_LABEL)) {
                        if ($attributeType && !empty($value)) {
                            $name                                               = $this->cleanupAttributeName($key);
                            $attributes[$attributeType][$name /* de */]['name'] = $value /* en */;
                        }

                        unset($product['attributes'][$key]);
                    }
                }

                $attributeType = null;

                foreach ($product['raw'] as $key => $value) {
                    $cleanKey = $this->cleanupAttributeName($key);

                    if ($key === self::CLOSEOUT_FIELD) {
                        $structuredProduct[self::CLOSEOUT_FIELD] = $value === self::CLOSEOUT_FIELD;
                    }

                    if ($key === self::CLOSEOUT_FIELD || $key === self::CLOSEOUT_IDENTIFIER) {
                        continue;
                    }

                    if (str_starts_with($key, self::ATTRIBUTE_IDENTIFIER_PROPERTY)) {
                        $attributeType = 'text';
                    } elseif (str_starts_with($key, self::ATTRIBUTE_IDENTIFIER_OPTION)) {
                        $attributeType = 'table';
                    }

                    if (str_contains($key, self::ATTRIBUTE_IDENTIFIER_PROPERTY)) {
                        continue;
                    }

                    if (str_contains($key, self::ATTRIBUTE_IDENTIFIER_OPTION)) {
                        continue;
                    }

                    if (str_contains($key, self::ATTRIBUTE_IDENTIFIER_LABEL)) {
                        continue;
                    }

                    if ($key === $manufacturerField && !empty($value) && empty($structuredProduct[$manufacturerField])) {
                        $structuredProduct[$manufacturerField] = $value;
                    }

                    if (array_key_exists($cleanKey, $attributes['text']) && !empty($value) && $attributeType === 'text') {
                        $groupId = md5(sprintf('%s-%s', PropertyGroupDefinition::ENTITY_NAME, $cleanKey));

                        try {
                            $optionId = $this->getMappedId($catalogMetadata, $cleanKey, $value, $productNumber);
                        } catch (\Throwable $exception) {
                            $runStatus = false;
                            $hasErrors = true;

                            $notificationErrors[] = $exception->getMessage();

                            continue;
                        }

                        $structuredProduct['properties'][$cleanKey]['name']     = $attributes['text'][$cleanKey]['name'];
                        $structuredProduct['properties'][$cleanKey]['value']    = $value;
                        $structuredProduct['properties'][$cleanKey]['groupId']  = $groupId;
                        $structuredProduct['properties'][$cleanKey]['optionId'] = $optionId;

                        $properties[$cleanKey]['name']               = $structuredProduct['properties'][$cleanKey]['name'];
                        $properties[$cleanKey]['options'][$optionId] = $structuredProduct['properties'][$cleanKey]['value'];
                        $properties[$cleanKey]['groupId']            = $groupId;
                    } elseif (array_key_exists($cleanKey, $attributes['table']) && !empty($value) && $attributeType === 'table') {
                        $groupId = md5(sprintf('%s-%s', PropertyGroupDefinition::ENTITY_NAME, $cleanKey));

                        try {
                            $optionId = $this->getMappedId($catalogMetadata, $cleanKey, $value, $productNumber);
                        } catch (\Throwable $exception) {
                            $runStatus = false;
                            $hasErrors = true;

                            $notificationErrors[] = $exception->getMessage();

                            continue;
                        }

                        $structuredProduct['options'][$cleanKey]['name']     = $attributes['table'][$cleanKey]['name'];
                        $structuredProduct['options'][$cleanKey]['value']    = $value;
                        $structuredProduct['options'][$cleanKey]['groupId']  = $groupId;
                        $structuredProduct['options'][$cleanKey]['optionId'] = $optionId;

                        $properties[$cleanKey]['name']               = $structuredProduct['options'][$cleanKey]['name'];
                        $properties[$cleanKey]['options'][$optionId] = $structuredProduct['options'][$cleanKey]['value'];
                        $properties[$cleanKey]['groupId']            = $groupId;
                    } elseif (!empty($value) || in_array($key, self::ALLOWED_EMPTY_FIELDS)) {
                        if (array_key_exists($key, $structuredProduct)) {
                            throw new \RuntimeException(sprintf('Duplicate key %s for product %s', $key, $product['raw'][self::PRODUCT_FIELD_NUMBER]));
                        }

                        $structuredProduct[$key] = $value;
                    }
                }

                // Property takes precedence over any standard field.
                if (array_key_exists('properties', $structuredProduct) && array_key_exists($manufacturerField, $structuredProduct['properties']) && !empty($structuredProduct['properties'][$manufacturerField]['value'])) {
                    $structuredProduct[$manufacturerField] = $structuredProduct['properties'][$manufacturerField]['value'];
                }

                $product['structured'] = $structuredProduct;
                unset($product['raw'], $product['attributes']);

                $variantStruct = new ProductStruct(
                    (string) $productNumber,
                    new ProductCollection(),
                    $product['structured'],
                    $filePath,
                    $catalogMetadata->getSortimentId(),
                    $catalogMetadata->getCatalogId(),
                    CrossSellingHelper::getCrossSellingGroups($product['structured'])
                );

                if (!empty($structuredProduct[$manufacturerField]) && is_string($structuredProduct[$manufacturerField])) {
                    $manufacturerName  = $structuredProduct[$manufacturerField];
                    $manufacturerImage = !empty($structuredProduct['Web Logo 1']) ? $structuredProduct['Web Logo 1'] : null;

                    if (empty($manufacturers[$manufacturerName])) {
                        $manufacturers[$manufacturerName]['name']  = $manufacturerName;
                        $manufacturers[$manufacturerName]['media'] = $manufacturerImage;
                    } elseif (empty($manufacturers[$manufacturerName]['media']) && !empty($manufacturerImage)) {
                        $manufacturers[$manufacturerName]['media'] = $manufacturerImage;
                    }
                }

                $mainProduct->getVariants()->set((string) $productNumber, $variantStruct);
            }
            unset($product);

            $products[] = $mainProduct;
        }
        unset($variants);

        $errors = $this->validateMappedData($catalogMetadata);

        if (!empty($errors)) {
            $runStatus = false;
            $hasErrors = true;

            foreach (array_values($errors) as $key => $error) {
                $notificationData['error_mapping_' . $key] = $error;
            }
        }

        $elementId = Uuid::randomHex();
        $this->runService->createNewElement(
            $elementId,
            implode('_', array_filter([
                $catalogMetadata->getSortimentId(),
                $catalogMetadata->getCatalogId(),
                $catalogMetadata->getLanguageCode(),
            ])),
            'parse_products',
            $context
        );

        $this->runService->markAsHandled(
            $elementId,
            !$hasErrors,
            $notificationData,
            $catalogMetadata->getArchivedFilename(),
            $context
        );

        $this->runService->finalizeRun($runStatus, $catalogMetadata->getArchivedFilename(), $context);

        if ($hasErrors) {
            $mailData = $notificationData;

            foreach (array_values(array_unique($notificationErrors)) as $key => $error) {
                $mailData['error_' . $key] = $error;
            }

            $this->notificationHelper->addNotification(
                'product pre processing failed',
                'parse_products',
                $mailData,
                $catalogMetadata
            );

            throw new \RuntimeException('Product parsing failed with errors');
        }

        return new ProductsStruct(new ProductCollection($products), $filePath, $properties, $manufacturers);
    }

    public function getCatalogMetadata(string $filePath, string $systemLanguageCode): CatalogMetadata
    {
        $filename = basename($filePath);
        $matches  = [];
        preg_match('/^((?<sortimentId>\d+)_)?(?<catalogId>\d+)_/', $filename, $matches);

        $catalogId = null;

        if (isset($matches['catalogId']) && $catalogId !== '') {
            $catalogId = $matches['catalogId'];
        }

        if ($catalogId === null) {
            throw new \RuntimeException(sprintf('Could not parse catalogId from %s', $filename));
        }

        $sortimentId = null;

        if (isset($matches['sortimentId']) && $matches['sortimentId'] !== '') {
            $sortimentId = $matches['sortimentId'];
        }

        $language = substr($filename, -5);

        return new CatalogMetadata($catalogId, $sortimentId, $language, $systemLanguageCode);
    }

    private function validateMappedData(CatalogMetadata $catalogMetadata): array
    {
        $currentMappingKey = implode('_', array_filter([
            $catalogMetadata->getCatalogId(),
            $catalogMetadata->getSortimentId(),
        ]));

        $errors = [];

        foreach (self::$propertyCounters as $mappingKey => $groupNames) {
            if ((string) $mappingKey !== $currentMappingKey) {
                continue;
            }

            foreach ($groupNames as $groupName => $mappingByLanguage) {
                if (count(array_unique($mappingByLanguage)) > 1) {
                    $errors[] = sprintf('Property amount in %s is not consistent', $groupName);
                }
            }
        }

        return $errors;
    }

    private function getMappedId(
        CatalogMetadata $catalogMetadata,
        string $groupName,
        string $optionValue,
        string $productNumber
    ): string {
        $mappingKey = implode('_', array_filter([
            $catalogMetadata->getCatalogId(),
            $catalogMetadata->getSortimentId(),
        ]));

        if (!isset(self::$propertyCounters[$mappingKey][$groupName][$catalogMetadata->getLanguageCode()])) {
            self::$propertyCounters[$mappingKey][$groupName][$catalogMetadata->getLanguageCode()] = 0;
        }

        $count = ++self::$propertyCounters[$mappingKey][$groupName][$catalogMetadata->getLanguageCode()];

        if ($catalogMetadata->isSystemLanguage()) {
            $uuid = md5(sprintf(
                '%s-%s-%s',
                PropertyGroupOptionDefinition::ENTITY_NAME,
                $groupName,
                $optionValue
            ));

            self::$propertyMapping[$mappingKey][$groupName][$count] = $uuid;
        }

        if (empty(self::$propertyMapping[$mappingKey][$groupName][$count])) {
            $error = 'Product %s: Could not find property mapping for %s in %s. ImportFile with system default language may be missing.';

            throw new \LogicException(sprintf($error, $productNumber, $optionValue, $groupName));
        }

        return self::$propertyMapping[$mappingKey][$groupName][$count];
    }

    private function cleanupAttributeName(string $name): string
    {
        $cleanedAttributeName = preg_replace(
            sprintf('/(%s)?\s?(\(\d+\))?$/', self::ATTRIBUTE_IDENTIFIER_LABEL),
            '',
            $name
        );

        if ($cleanedAttributeName === null) {
            throw new \RuntimeException(sprintf('%s could not be parsed', $name));
        }

        return str_replace('.', '', $cleanedAttributeName);
    }

    private function getItems(string $filePath, string $pointer): Items
    {
        return Items::fromFile($filePath, ['pointer' => $pointer, 'decoder' => new ExtJsonDecoder(true)]);
    }
}
