<?php

declare(strict_types=1);

namespace ReiffIntegrations\MeDaPro\DataAbstractionLayer;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                     add(ReiffCategoryEntity $entity)
 * @method void                     set(string $key, ReiffCategoryEntity $entity)
 * @method ReiffCategoryEntity[]    getIterator()
 * @method ReiffCategoryEntity[]    getElements()
 * @method null|ReiffCategoryEntity get(string $key)
 * @method null|ReiffCategoryEntity first()
 * @method null|ReiffCategoryEntity last()
 */
class ReiffCategoryCollection extends EntityCollection
{
    public function getExpectedClass(): string
    {
        return ReiffCategoryEntity::class;
    }
}
