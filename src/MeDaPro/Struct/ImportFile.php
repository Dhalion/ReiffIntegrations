<?php

declare(strict_types=1);

namespace ReiffIntegrations\MeDaPro\Struct;

use Shopware\Core\Framework\Struct\Struct;
use Symfony\Component\Finder\SplFileInfo;

class ImportFile extends Struct
{
    public function __construct(
        protected readonly SplFileInfo $file,
        protected readonly CatalogMetadata $catalogMetadata,
        protected readonly int $position
    ) {
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
