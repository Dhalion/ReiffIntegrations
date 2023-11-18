<?php

declare(strict_types=1);

namespace ReiffIntegrations\MeDaPro\Struct;

use Shopware\Core\Framework\Struct\Struct;

class ProductsStruct extends Struct
{
    public function __construct(
        private readonly ProductCollection $products,
        private readonly string $filePath,
        private readonly array $properties,
        private readonly array $manufacturers,
    ) {
    }

    public function getProducts(): ProductCollection
    {
        return $this->products;
    }

    public function getProductNumbers(): array
    {
        return $this->products->map(function(ProductStruct $productStruct) {return $productStruct->getProductNumber();});
    }

    public function getVariantProductNumbers(): array
    {
        $variantNumbers = [];
        foreach($this->getProducts() as $product) {
            foreach($product->getVariants() as $variant) {
                $variantNumbers[] = $variant->getProductNumber();
            }
        }

        return $variantNumbers;
    }

    public function getAllProductNumbers(): array
    {
        return array_merge($this->getProductNumbers(), $this->getVariantProductNumbers());
    }

    public function getFilePath(): string
    {
        return $this->filePath;
    }

    public function getProperties(): array
    {
        return $this->properties;
    }

    public function getManufacturers(): array
    {
        return $this->manufacturers;
    }
}
