<?php

declare(strict_types=1);

namespace ReiffIntegrations\Sap\Contract\Struct\Status;

use Shopware\Core\Framework\Struct\Collection;

/**
 * @method void                       add(ContractAddressStruct $struct)
 * @method void                       set(string $key, ContractAddressStruct $struct)
 * @method ContractAddressStruct[]    getIterator()
 * @method ContractAddressStruct[]    getElements()
 * @method null|ContractAddressStruct get(string $key)
 * @method null|ContractAddressStruct first()
 * @method null|ContractAddressStruct last()
 */
class ContractAddressCollection extends Collection
{
    public function getExpectedClass(): string
    {
        return ContractAddressStruct::class;
    }
}
