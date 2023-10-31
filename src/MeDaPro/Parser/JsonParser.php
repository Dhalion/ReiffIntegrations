<?php

declare(strict_types=1);

namespace ReiffIntegrations\MeDaPro\Parser;

use JsonMachine\Items;
use JsonMachine\JsonDecoder\ExtJsonDecoder;
use ReiffIntegrations\MeDaPro\Helper\CrossSellingHelper;
use ReiffIntegrations\MeDaPro\Struct\CatalogMetadata;
use ReiffIntegrations\MeDaPro\Struct\CatalogStruct;
use ReiffIntegrations\MeDaPro\Struct\CategoryCollection;
use ReiffIntegrations\MeDaPro\Struct\CategoryStruct;
use ReiffIntegrations\MeDaPro\Struct\ProductCollection;
use ReiffIntegrations\MeDaPro\Struct\ProductsStruct;
use ReiffIntegrations\MeDaPro\Struct\ProductStruct;
use ReiffIntegrations\Util\LockHandler;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionDefinition;
use Shopware\Core\Content\Property\PropertyGroupDefinition;
use Shopware\Core\Framework\Uuid\Uuid;

class JsonParser
{
    private const CATEGORY_TYPE_VARIATION_GROUP = 'variationGroup';
    private const ATTRIBUTE_IDENTIFIER_PROPERTY = 'Textattribute';
    private const ATTRIBUTE_IDENTIFIER_OPTION = 'Tabellenattribute';
    private const ATTRIBUTE_IDENTIFIER_LABEL = ' Name';
    private const PRODUCT_FIELD_NUMBER = 'Artikel-Nr';
    private const ATTRIBUTE_PREFIX_MANUFACTURER = 'Hersteller';
    private const CLOSEOUT_FIELD = 'Lagerverkauf';
    private const CLOSEOUT_IDENTIFIER = 'Lagerverkauf Name';
    private const ALLOWED_EMPTY_FIELDS = [ // We have to keep these fields in the structs to know if they are empty strings
        'Button CAD',
    ];

    private static array $propertyMapping = [];
    private static array $propertyCounters = [];

    public function getCategories(string $filePath, CatalogMetadata $catalogMetadata): ?CatalogStruct
    {
        $catalogId = $catalogMetadata->getCatalogId();
        $sortimentId = $catalogMetadata->getSortimentId();

        if ($catalogId === null) {
            return null;
        }

        $categoryData = $this->getItems($filePath, '/catalogNodes');
        $categories = [];

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

    public function getProducts(string $filePath, CatalogMetadata $catalogMetadata): ProductsStruct
    {
        $rawProducts = [];
        $products = [];
        $catalogNodes = [];
        $properties = [];

        foreach ($this->getItems($filePath, '/catalogNodes') as $catalogNode) {
            $catalogNodes[$catalogNode['id']] = $catalogNode;
        }

        foreach ($this->getItems($filePath, '/articles') as $product) {
            $variationGroupId = $catalogNodes[$product['variationGroupId']]['uId'];
            $rawProducts[$variationGroupId][$product[self::PRODUCT_FIELD_NUMBER]]['raw'] = $product;
            $rawProducts[$variationGroupId][$product[self::PRODUCT_FIELD_NUMBER]]['raw']['category'] = $catalogNodes[$catalogNodes[$product['variationGroupId']]['parentId']]['uId'];
            $rawProducts[$variationGroupId][$product[self::PRODUCT_FIELD_NUMBER]]['attributes'] = $product;
        }

        foreach ($rawProducts as &$variants) {
            $firstVariant = reset($variants);

            if (array_key_exists(self::ATTRIBUTE_PREFIX_MANUFACTURER, $firstVariant['raw'])) {
                unset($firstVariant['raw'][self::ATTRIBUTE_PREFIX_MANUFACTURER]);  // Make sure main product has no manufacturer to prevent inheritance
            }

            if (array_key_exists(self::ATTRIBUTE_PREFIX_MANUFACTURER, $firstVariant['attributes'])) {
                unset($firstVariant['attributes'][self::ATTRIBUTE_PREFIX_MANUFACTURER]);
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
                $attributeType = null;
                $attributes = ['text' => [], 'table' => []];
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
                            $name = $this->cleanupAttributeName($key);
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

                    if ($key === self::ATTRIBUTE_PREFIX_MANUFACTURER && !empty($value) && empty($structuredProduct[self::ATTRIBUTE_PREFIX_MANUFACTURER])) {
                        $structuredProduct[self::ATTRIBUTE_PREFIX_MANUFACTURER] = $value;
                    }

                    if (array_key_exists($cleanKey, $attributes['text']) && !empty($value) && $attributeType === 'text') {
                        $groupId = md5(sprintf('%s-%s', PropertyGroupDefinition::ENTITY_NAME, $cleanKey));
                        $optionId = $this->getMappedId($catalogMetadata, $cleanKey, $value);

                        $structuredProduct['properties'][$cleanKey]['name'] = $attributes['text'][$cleanKey]['name'];
                        $structuredProduct['properties'][$cleanKey]['value'] = $value;
                        $structuredProduct['properties'][$cleanKey]['groupId'] = $groupId;
                        $structuredProduct['properties'][$cleanKey]['optionId'] =  $optionId;

                        $properties[$cleanKey]['name'] = $structuredProduct['properties'][$cleanKey]['name'];
                        $properties[$cleanKey]['options'][$optionId] = $structuredProduct['properties'][$cleanKey]['value'];
                        $properties[$cleanKey]['groupId'] = $groupId;

                    } elseif (array_key_exists($cleanKey, $attributes['table']) && !empty($value) && $attributeType === 'table') {
                        $groupId = md5(sprintf('%s-%s', PropertyGroupDefinition::ENTITY_NAME, $cleanKey));
                        $optionId = $this->getMappedId($catalogMetadata, $cleanKey, $value);

                        $structuredProduct['options'][$cleanKey]['name'] = $attributes['table'][$cleanKey]['name'];
                        $structuredProduct['options'][$cleanKey]['value'] = $value;
                        $structuredProduct['options'][$cleanKey]['groupId'] = $groupId;
                        $structuredProduct['options'][$cleanKey]['optionId'] =  $optionId;

                        $properties[$cleanKey]['name'] = $structuredProduct['options'][$cleanKey]['name'];
                        $properties[$cleanKey]['options'][$optionId] = $structuredProduct['options'][$cleanKey]['value'];
                        $properties[$cleanKey]['groupId'] = $groupId;

                    } elseif (!empty($value) || in_array($key, self::ALLOWED_EMPTY_FIELDS)) {
                        if (array_key_exists($key, $structuredProduct)) {
                            throw new \RuntimeException(
                                sprintf(
                                    'Duplicate key %s for product %s',
                                    $key,
                                    $product['raw'][self::PRODUCT_FIELD_NUMBER]
                                )
                            );
                        }

                        $structuredProduct[$key] = $value;
                    }
                }

                // Property takes precedence over any standard field.
                if (array_key_exists('properties', $structuredProduct) && array_key_exists(
                        self::ATTRIBUTE_PREFIX_MANUFACTURER,
                        $structuredProduct['properties']
                    ) && !empty($structuredProduct['properties'][self::ATTRIBUTE_PREFIX_MANUFACTURER]['value'])) {
                    $structuredProduct[self::ATTRIBUTE_PREFIX_MANUFACTURER] = $structuredProduct['properties'][self::ATTRIBUTE_PREFIX_MANUFACTURER]['value'];
                }

                $product['structured'] = $structuredProduct;
                unset($product['raw'], $product['attributes']);

                $variantStruct = new ProductStruct(
                    (string)$productNumber,
                    new ProductCollection(),
                    $product['structured'],
                    $filePath,
                    $catalogMetadata->getSortimentId(),
                    $catalogMetadata->getCatalogId(),
                    CrossSellingHelper::getCrossSellingGroups($product['structured'])
                );

                $mainProduct->getVariants()->set((string)$productNumber, $variantStruct);
            }
            unset($product);

            $products[] = $mainProduct;
        }
        unset($variants);

        $this->validateMappedData();

        return new ProductsStruct(new ProductCollection($products), $filePath, $properties);
    }

    public function getCatalogMetadata(string $filePath, string $systemLanguageCode): CatalogMetadata
    {
        $filename = basename($filePath);
        $matches = [];
        preg_match('/^((?<sortimentId>\d+)_)?(?<catalogId>\d+)_/', $filename, $matches);

        $catalogId = null;

        if (isset($matches['catalogId']) && $catalogId !== '') {
            $catalogId = $matches['catalogId'];
        }

        if (null === $catalogId) {
            throw new \RuntimeException(sprintf('Could not parse catalogId from %s', $filename));
        }

        $sortimentId = null;

        if (isset($matches['sortimentId']) && $matches['sortimentId'] !== '') {
            $sortimentId = $matches['sortimentId'];
        }

        $language = substr($filename, -5);

        return new CatalogMetadata($catalogId, $sortimentId, $language, $systemLanguageCode);
    }

    private function validateMappedData(): void
    {
        foreach (self::$propertyCounters as $mappingKey => $languages) {
            if (count(array_unique($languages)) > 1) {
                throw new \RuntimeException(sprintf('Property amount for %s is not consistent', $mappingKey));
            }
        }
    }

    private function getMappedId(CatalogMetadata $catalogMetadata, $groupName, $optionValue): string
    {
        $mappingKey = implode("_", array_filter([
            $catalogMetadata->getCatalogId(),
            $catalogMetadata->getSortimentId()
        ]));

        if (!isset(self::$propertyCounters[$mappingKey][$catalogMetadata->getLanguageCode()])) {
            self::$propertyCounters[$mappingKey][$catalogMetadata->getLanguageCode()] = 0;
        }

        $count = ++self::$propertyCounters[$mappingKey][$catalogMetadata->getLanguageCode()];

        if ($catalogMetadata->isSystemLanguage()) {
            $uuid = md5(sprintf(
                '%s-%s-%s',
                PropertyGroupOptionDefinition::ENTITY_NAME,
                $groupName,
                $optionValue
            ));

            self::$propertyMapping[$mappingKey][$count] = $uuid;
        }

        if (empty(self::$propertyMapping[$mappingKey][$count])) {
            $error = 'Could not find mapping for %s. ImportFile with system default language may be missing';

            throw new \LogicException(sprintf($error, $groupName));
        }

        return self::$propertyMapping[$mappingKey][$count];
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
