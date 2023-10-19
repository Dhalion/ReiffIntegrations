<?php

declare(strict_types=1);

namespace ReiffIntegrations\MeDaPro\DataAbstractionLayer;

use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;

class ReiffCategoryEntity extends Entity
{
    protected ?CategoryEntity $category = null;
    protected ?string $categoryId       = null;
    protected ?string $catalogId        = null;
    protected ?string $uId              = null;

    public function getCategory(): ?CategoryEntity
    {
        return $this->category;
    }

    public function setCategory(?CategoryEntity $category): void
    {
        $this->category = $category;
    }

    public function getCategoryId(): ?string
    {
        return $this->categoryId;
    }

    public function setCategoryId(?string $categoryId): void
    {
        $this->categoryId = $categoryId;
    }

    public function getCatalogId(): ?string
    {
        return $this->catalogId;
    }

    public function setCatalogId(?string $catalogId): void
    {
        $this->catalogId = $catalogId;
    }

    public function getUId(): ?string
    {
        return $this->uId;
    }

    public function setUId(?string $uId): void
    {
        $this->uId = $uId;
    }
}
