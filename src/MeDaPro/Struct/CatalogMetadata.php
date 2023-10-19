<?php

declare(strict_types=1);

namespace ReiffIntegrations\MeDaPro\Struct;

use Shopware\Core\Framework\Struct\Struct;

class CatalogMetadata extends Struct
{
    private ?string $catalogId   = null;
    private ?string $sortimentId = null;

    public function __construct(?string $catalogId, ?string $sortimentId)
    {
        $this->catalogId   = $catalogId;
        $this->sortimentId = $sortimentId;
    }

    public function getCatalogId(): ?string
    {
        return $this->catalogId;
    }

    public function getSortimentId(): ?string
    {
        return $this->sortimentId;
    }
}
