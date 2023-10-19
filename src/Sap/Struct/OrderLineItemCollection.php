<?php

declare(strict_types=1);

namespace ReiffIntegrations\Sap\Struct;

use Shopware\Core\Framework\Struct\Collection;

/**
 * @method void                     add(OrderLineItemStruct $struct)
 * @method void                     set(string $key, OrderLineItemStruct $struct)
 * @method OrderLineItemStruct[]    getIterator()
 * @method OrderLineItemStruct[]    getElements()
 * @method null|OrderLineItemStruct get(string $key)
 * @method null|OrderLineItemStruct first()
 * @method null|OrderLineItemStruct last()
 */
class OrderLineItemCollection extends Collection
{
    public function getExpectedClass(): string
    {
        return OrderLineItemStruct::class;
    }
}
