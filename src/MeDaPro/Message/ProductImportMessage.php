<?php

declare(strict_types=1);

namespace ReiffIntegrations\MeDaPro\Message;

use ReiffIntegrations\MeDaPro\Struct\CatalogMetadata;
use ReiffIntegrations\MeDaPro\Struct\ProductStruct;
use ReiffIntegrations\Util\Message\AbstractImportMessage;
use Shopware\Core\Framework\Context;

class ProductImportMessage
{
    public function __construct(
        private readonly ProductStruct $product,
        private readonly string           $archivedFileName,
        private readonly CatalogMetadata $catalogMetadata,
        private readonly Context          $context,
        private readonly string $elementId,
    ) {
    }

    public function getArchivedFileName(): string
    {
        return $this->archivedFileName;
    }

    public function getCatalogMetadata(): CatalogMetadata
    {
        return $this->catalogMetadata;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getProduct(): ProductStruct
    {
        return $this->product;
    }

    public function getElementId(): string
    {
        return $this->elementId;
    }
}
