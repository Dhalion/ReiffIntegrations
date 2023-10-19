<?php

declare(strict_types=1);

namespace ReiffIntegrations\Sap\Struct;

use Shopware\Core\Framework\Struct\Collection;

/**
 * @method void             add(OrderStruct $struct)
 * @method void             set(string $key, OrderStruct $struct)
 * @method OrderStruct[]    getIterator()
 * @method OrderStruct[]    getElements()
 * @method null|OrderStruct get(string $key)
 * @method null|OrderStruct first()
 * @method null|OrderStruct last()
 */
class OrderCollection extends Collection
{
    public function getExpectedClass(): string
    {
        return OrderStruct::class;
    }
}
