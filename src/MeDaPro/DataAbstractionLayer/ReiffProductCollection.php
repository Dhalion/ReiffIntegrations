<?php

declare(strict_types=1);

namespace ReiffIntegrations\MeDaPro\DataAbstractionLayer;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                    add(ReiffProductEntity $entity)
 * @method void                    set(string $key, ReiffProductEntity $entity)
 * @method ReiffProductEntity[]    getIterator()
 * @method ReiffProductEntity[]    getElements()
 * @method null|ReiffProductEntity get(string $key)
 * @method null|ReiffProductEntity first()
 * @method null|ReiffProductEntity last()
 */
class ReiffProductCollection extends EntityCollection
{
    public function getExpectedClass(): string
    {
        return ReiffProductEntity::class;
    }
}
