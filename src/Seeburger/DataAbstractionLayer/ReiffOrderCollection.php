<?php

declare(strict_types=1);

namespace ReiffIntegrations\Seeburger\DataAbstractionLayer;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                  add(ReiffOrderEntity $entity)
 * @method void                  set(string $key, ReiffOrderEntity $entity)
 * @method ReiffOrderEntity[]    getIterator()
 * @method ReiffOrderEntity[]    getElements()
 * @method null|ReiffOrderEntity get(string $key)
 * @method null|ReiffOrderEntity first()
 * @method null|ReiffOrderEntity last()
 */
class ReiffOrderCollection extends EntityCollection
{
    public function getExpectedClass(): string
    {
        return ReiffOrderEntity::class;
    }
}
