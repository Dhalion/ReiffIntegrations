<?php

declare(strict_types=1);

namespace ReiffIntegrations\Sap\DataAbstractionLayer;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                     add(ReiffCustomerEntity $entity)
 * @method void                     set(string $key, ReiffCustomerEntity $entity)
 * @method ReiffCustomerEntity[]    getIterator()
 * @method ReiffCustomerEntity[]    getElements()
 * @method null|ReiffCustomerEntity get(string $key)
 * @method null|ReiffCustomerEntity first()
 * @method null|ReiffCustomerEntity last()
 */
class ReiffCustomerCollection extends EntityCollection
{
    public function getExpectedClass(): string
    {
        return ReiffCustomerEntity::class;
    }
}
