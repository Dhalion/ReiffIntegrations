<?php

declare(strict_types=1);

namespace ReiffIntegrations\MeDaPro\Struct;

use Shopware\Core\Framework\Struct\Struct;
use Symfony\Component\Finder\SplFileInfo;

class ImportFile extends Struct
{
    protected SplFileInfo $file;
    protected CatalogMetadata $catalogMetadata;
    protected int $position = 0;

    public function __construct(SplFileInfo $file, CatalogMetadata $catalogMetadata, int $position)
    {
        $this->file            = $file;
        $this->catalogMetadata = $catalogMetadata;
        $this->position        = $position;
    }

    public function getFile(): SplFileInfo
    {
        return $this->file;
    }

    public function getCatalogMetadata(): CatalogMetadata
    {
        return $this->catalogMetadata;
    }

    public function getPosition(): int
    {
        return $this->position;
    }
}
