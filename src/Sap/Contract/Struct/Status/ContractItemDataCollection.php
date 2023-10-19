<?php

declare(strict_types=1);

namespace ReiffIntegrations\Sap\Contract\Struct\Status;

use Shopware\Core\Framework\Struct\Collection;

/**
 * @method void                        add(ContractItemDataStruct $struct)
 * @method void                        set(string $key, ContractItemDataStruct $struct)
 * @method ContractItemDataStruct[]    getIterator()
 * @method ContractItemDataStruct[]    getElements()
 * @method null|ContractItemDataStruct get(string $key)
 * @method null|ContractItemDataStruct first()
 * @method null|ContractItemDataStruct last()
 */
class ContractItemDataCollection extends Collection
{
    public function getExpectedClass(): string
    {
        return ContractItemDataStruct::class;
    }
}
