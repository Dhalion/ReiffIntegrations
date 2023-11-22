<?php

declare(strict_types=1);

namespace ReiffIntegrations\MeDaPro\Struct;

use Shopware\Core\Framework\Struct\Struct;

class ProductStruct extends Struct
{
    public function __construct(
        protected readonly string $productNumber,
        protected readonly ProductCollection $variants,
        protected readonly array $data,
        protected readonly string $filePath,
        protected readonly ?string $sortimentId,
        protected readonly ?string $catalogId,
        protected readonly array $crossSellingGroups = []
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

    public function getDataByKey(string $key): null|string|array|bool
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
