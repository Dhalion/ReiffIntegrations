<?php

declare(strict_types=1);

namespace ReiffIntegrations\MeDaPro\DataAbstractionLayer;

use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;

class ReiffProductEntity extends Entity
{
    protected ?ProductEntity $product   = null;
    protected ?string $productId        = null;
    protected ?bool $inDefaultSortiment = null;

    public function getProduct(): ?ProductEntity
    {
        return $this->product;
    }

    public function setProduct(?ProductEntity $product): void
    {
        $this->product = $product;
    }

    public function getProductId(): ?string
    {
        return $this->productId;
    }

    public function setProductId(?string $productId): void
    {
        $this->productId = $productId;
    }

    public function getInDefaultSortiment(): bool
    {
        return (bool) $this->inDefaultSortiment;
    }

    public function setInDefaultSortiment(bool $inDefaultSortiment): void
    {
        $this->inDefaultSortiment = $inDefaultSortiment;
    }
}
