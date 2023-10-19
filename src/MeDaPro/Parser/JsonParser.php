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

class JsonParser
{
    private const CATEGORY_TYPE_VARIATION_GROUP = 'variationGroup';
    private const ATTRIBUTE_IDENTIFIER_PROPERTY = 'Textattribute';
    private const ATTRIBUTE_IDENTIFIER_OPTION = 'Tabellenattribute';
    private const ATTRIBUTE_IDENTIFIER_LABEL = ' Name';
    private const PRODUCT_FIELD_NUMBER = 'Artikel-Nr';
    private const ATTRIBUTE_IDENTIFIER_COLOR = 'TradePro Farbe';
    private const ATTRIBUTE_NAME_COLOR = 'Farbe';
    private const ATTRIBUTE_PREFIX_MANUFACTURER = 'Hersteller';
    private const CLOSEOUT_FIELD = 'Lagerverkauf';
    private const CLOSEOUT_IDENTIFIER = 'Lagerverkauf Name';
    private const ALLOWED_EMPTY_FIELDS = [ // We have to keep these fields in the structs to know if they are empty strings
        'Button CAD',
    ];

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

    public function getProducts(string $filePath): ProductsStruct
    {
        $rawProducts = [];
        $products = [];
        $catalogNodes = [];
        $properties = [];
        $catalogMetadata = $this->getCatalogMetadata($filePath);

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
                unset($firstVariant['raw'][self::CLOSEOUT_IDENTIFIER]);
            }

            if (array_key_exists(self::CLOSEOUT_IDENTIFIER, $firstVariant['attributes'])) {
                unset($firstVariant['attributes'][self::CLOSEOUT_IDENTIFIER]);
            }

            if (array_key_exists(self::CLOSEOUT_FIELD, $firstVariant['raw'])) {
                unset($firstVariant['raw'][self::CLOSEOUT_FIELD]);
            }

            if (array_key_exists(self::CLOSEOUT_FIELD, $firstVariant['attributes'])) {
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
                            $attributes[$attributeType][$name]['name'] = $value;
                        }

                        unset($product['attributes'][$key]);
                    }
                }

                $attributeType = null;
                foreach ($product['raw'] as $key => $value) {
                    $cleanKey = $this->cleanupAttributeName($key);

                    if ($key === self::CLOSEOUT_FIELD) {
                        $structuredProduct[self::CLOSEOUT_FIELD] = $value;
                    }

                    if ($key === self::CLOSEOUT_FIELD || $key === self::CLOSEOUT_IDENTIFIER) {
                        continue;
                    }

                    if (str_starts_with($key, self::ATTRIBUTE_IDENTIFIER_PROPERTY)) {
                        $attributeType = 'text';
                    } elseif (str_starts_with($key, self::ATTRIBUTE_IDENTIFIER_OPTION)) {
                        $attributeType = 'table';
                    }

                    if (str_contains($key, self::ATTRIBUTE_IDENTIFIER_PROPERTY) || str_contains(
                            $key,
                            self::ATTRIBUTE_IDENTIFIER_OPTION
                        ) || str_contains($key, self::ATTRIBUTE_IDENTIFIER_LABEL)) {
                        continue;
                    }

                    if ($key === self::ATTRIBUTE_PREFIX_MANUFACTURER && !empty($value) && empty($structuredProduct[self::ATTRIBUTE_PREFIX_MANUFACTURER])) {
                        $structuredProduct[self::ATTRIBUTE_PREFIX_MANUFACTURER] = $value;
                    }

                    if (array_key_exists(
                            $cleanKey,
                            $attributes['text']
                        ) && !empty($value) && $attributeType === 'text') {
                        $structuredProduct['properties'][$cleanKey]['name'] = $attributes['text'][$cleanKey]['name'];
                        $structuredProduct['properties'][$cleanKey]['value'] = $value;

                        $properties[$cleanKey]['name'] = $structuredProduct['properties'][$cleanKey]['name'];
                        $properties[$cleanKey]['options'][$structuredProduct['properties'][$cleanKey]['value']] = $structuredProduct['properties'][$cleanKey]['value'];
                    } elseif (array_key_exists(
                            $cleanKey,
                            $attributes['table']
                        ) && !empty($value) && $attributeType === 'table') {
                        $structuredProduct['options'][$cleanKey]['name'] = $attributes['table'][$cleanKey]['name'];
                        $structuredProduct['options'][$cleanKey]['value'] = $value;

                        $properties[$cleanKey]['name'] = $structuredProduct['options'][$cleanKey]['name'];
                        $properties[$cleanKey]['options'][$structuredProduct['options'][$cleanKey]['value']] = $structuredProduct['options'][$cleanKey]['value'];
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

                    if ($key === self::ATTRIBUTE_IDENTIFIER_COLOR && !empty($value)) {
                        $structuredProduct['properties'][$cleanKey]['name'] = self::ATTRIBUTE_NAME_COLOR;
                        $structuredProduct['properties'][$cleanKey]['value'] = $value;

                        $properties[$cleanKey]['name'] = $structuredProduct['properties'][$cleanKey]['name'];
                        $properties[$cleanKey]['options'][$structuredProduct['properties'][$cleanKey]['value']] = $structuredProduct['properties'][$cleanKey]['value'];
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

        return new ProductsStruct(new ProductCollection($products), $filePath, $properties);
    }

    public function getCatalogMetadata(string $filePath): CatalogMetadata
    {
        $filename = basename($filePath);
        $matches = [];
        preg_match('/^((?<sortimentId>\d+)_)?(?<catalogId>\d+)_/', $filename, $matches);

        $catalogId = null;

        if (isset($matches['catalogId']) && $catalogId !== '') {
            $catalogId = $matches['catalogId'];
        }

        $sortimentId = null;

        if (isset($matches['sortimentId']) && $matches['sortimentId'] !== '') {
            $sortimentId = $matches['sortimentId'];
        }

        return new CatalogMetadata($catalogId, $sortimentId);
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
