<?php

declare(strict_types=1);

namespace ReiffIntegrations\MeDaPro\DataAbstractionLayer;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                  add(ReiffMediaEntity $entity)
 * @method void                  set(string $key, ReiffMediaEntity $entity)
 * @method ReiffMediaEntity[]    getIterator()
 * @method ReiffMediaEntity[]    getElements()
 * @method null|ReiffMediaEntity get(string $key)
 * @method null|ReiffMediaEntity first()
 * @method null|ReiffMediaEntity last()
 */
class ReiffMediaCollection extends EntityCollection
{
    public function getExpectedClass(): string
    {
        return ReiffMediaEntity::class;
    }
}
