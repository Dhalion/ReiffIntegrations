<?php

declare(strict_types=1);

namespace ReiffIntegrations\Sap\CustomOrderNumber\Struct;

use Shopware\Core\Framework\Struct\Collection;

/**
 * @method void                   add(OrderNumberStruct $struct)
 * @method void                   set(string $key, OrderNumberStruct $struct)
 * @method OrderNumberStruct[]    getIterator()
 * @method OrderNumberStruct[]    getElements()
 * @method null|OrderNumberStruct get(string $key)
 * @method null|OrderNumberStruct first()
 * @method null|OrderNumberStruct last()
 */
class OrderNumberCollection extends Collection
{
    public function getExpectedClass(): string
    {
        return OrderNumberStruct::class;
    }
}
