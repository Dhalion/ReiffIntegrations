<?php

declare(strict_types=1);

namespace ReiffIntegrations\Sap\Struct;

use Shopware\Core\Framework\Struct\Collection;

/**
 * @method void                    add(OrderAddressStruct $struct)
 * @method void                    set(string $key, OrderAddressStruct $struct)
 * @method OrderAddressStruct[]    getIterator()
 * @method OrderAddressStruct[]    getElements()
 * @method null|OrderAddressStruct get(string $key)
 * @method null|OrderAddressStruct first()
 * @method null|OrderAddressStruct last()
 */
class OrderAddressCollection extends Collection
{
    public function getExpectedClass(): string
    {
        return OrderAddressStruct::class;
    }
}
