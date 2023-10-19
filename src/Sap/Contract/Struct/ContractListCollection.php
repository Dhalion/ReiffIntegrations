<?php

declare(strict_types=1);

namespace ReiffIntegrations\Sap\Contract\Struct;

use Shopware\Core\Framework\Struct\Collection;

/**
 * @method void                    add(ContractListStruct $struct)
 * @method void                    set(string $key, ContractListStruct $struct)
 * @method ContractListStruct[]    getIterator()
 * @method ContractListStruct[]    getElements()
 * @method null|ContractListStruct get(string $key)
 * @method null|ContractListStruct first()
 * @method null|ContractListStruct last()
 */
class ContractListCollection extends Collection
{
    public function getExpectedClass(): string
    {
        return ContractListStruct::class;
    }
}
