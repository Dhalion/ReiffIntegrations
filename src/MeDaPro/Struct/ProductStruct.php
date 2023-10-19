<?php

declare(strict_types=1);

namespace ReiffIntegrations\MeDaPro\Struct;

use Shopware\Core\Framework\Struct\Struct;

class ProductStruct extends Struct
{
    public function __construct(
        protected string $productNumber,
        protected ProductCollection $variants,
        protected array $data,
        protected string $filePath,
        protected ?string $sortimentId,
        protected ?string $catalogId,
        protected array $crossSellingGroups = []
    ) {
    }

    public function getProductNumber(): string
    {
        return $this->productNumber;
    }

    public function getVariants(): ProductCollection
    {
        return $this->variants;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function setData(array $data): void
    {
        $this->data = $data;
    }

    public function getDataByKey(string $key): null|string|array
    {
        return $this->data[$key] ?? null;
    }

    public function setDataByKey(string $key, string $value): void
    {
        $this->data[$key] = $value;
    }

    public function getFilePath(): string
    {
        return $this->filePath;
    }

    public function getSortimentId(): ?string
    {
        return $this->sortimentId;
    }

    public function getCatalogId(): ?string
    {
        return $this->catalogId;
    }

    public function getCrossSellingGroups(): array
    {
        return $this->crossSellingGroups;
    }
}
