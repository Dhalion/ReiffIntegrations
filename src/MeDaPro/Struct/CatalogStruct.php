<?php

declare(strict_types=1);

namespace ReiffIntegrations\MeDaPro\Struct;

use Shopware\Core\Framework\Struct\Struct;

class CatalogStruct extends Struct
{
    protected string $id;
    protected CategoryCollection $categories;
    protected string $filePath;
    protected ?string $sortimentId;

    public function __construct(
        string $id,
        CategoryCollection $categories,
        string $filePath,
        ?string $sortimentId = null
    )  {
        $this->id = $id;
        $this->categories = $categories;
        $this->filePath = $filePath;
        $this->sortimentId = $sortimentId;
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
