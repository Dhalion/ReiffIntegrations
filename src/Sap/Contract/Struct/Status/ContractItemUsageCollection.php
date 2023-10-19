<?php

declare(strict_types=1);

namespace ReiffIntegrations\Sap\Contract\Struct\Status;

use Shopware\Core\Framework\Struct\Collection;

/**
 * @method void                         add(ContractItemUsageStruct $struct)
 * @method void                         set(string $key, ContractItemUsageStruct $struct)
 * @method ContractItemUsageStruct[]    getIterator()
 * @method ContractItemUsageStruct[]    getElements()
 * @method null|ContractItemUsageStruct get(string $key)
 * @method null|ContractItemUsageStruct first()
 * @method null|ContractItemUsageStruct last()
 */
class ContractItemUsageCollection extends Collection
{
    public function getExpectedClass(): string
    {
        return ContractItemUsageStruct::class;
    }
}
