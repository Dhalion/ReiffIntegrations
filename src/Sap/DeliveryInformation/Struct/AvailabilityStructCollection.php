<?php

declare(strict_types=1);

namespace ReiffIntegrations\Sap\DeliveryInformation\Struct;

use Shopware\Core\Framework\Struct\Collection;

/**
 * @method void                    add(AvailabilityStruct $struct)
 * @method void                    set(string $key, AvailabilityStruct $struct)
 * @method AvailabilityStruct[]    getIterator()
 * @method AvailabilityStruct[]    getElements()
 * @method null|AvailabilityStruct get(string $key)
 * @method null|AvailabilityStruct first()
 * @method null|AvailabilityStruct last()
 */
class AvailabilityStructCollection extends Collection
{
    public function getExpectedClass(): string
    {
        return AvailabilityStruct::class;
    }

    public function getAvailabilityByNumber(string $productNumber): ?AvailabilityStruct
    {
        foreach ($this->getElements() as $availabilityStruct) {
            if ($availabilityStruct->getProductNumber() === $productNumber) {
                return $availabilityStruct;
            }
        }

        return null;
    }
}
