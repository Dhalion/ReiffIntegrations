<?php

declare(strict_types=1);

namespace ReiffIntegrations\MeDaPro\Struct;

use Shopware\Core\Framework\Struct\Struct;

class CatalogStruct extends Struct
{
    public function __construct(
        private readonly string $id,
        private readonly CategoryCollection $categories,
        private readonly string $filePath,
        private readonly ?string $sortimentId,
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
