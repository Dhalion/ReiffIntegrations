<?php

declare(strict_types=1);

namespace ReiffIntegrations\MeDaPro\Struct;

use Shopware\Core\Framework\Struct\Struct;

class CatalogStruct extends Struct
{
    public function __construct(
        protected readonly string $id,
        protected readonly CategoryCollection $categories,
        protected readonly string $filePath,
        protected readonly ?string $sortimentId = null
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getCategories(): CategoryCollection
    {
        return $this->categories;
    }

    public function getFilePath(): string
    {
        return $this->filePath;
    }

    public function getSortimentId(): ?string
    {
        return $this->sortimentId;
    }
}
