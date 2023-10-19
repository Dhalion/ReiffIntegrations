<?php

declare(strict_types=1);

namespace ReiffIntegrations\Sap\Struct;

use Shopware\Core\Framework\Struct\Struct;

class OrderAddressStruct extends Struct
{
    public const ADDRESS_TYPES = [
        'SOLD_TO',
        'BILL_TO',
        'SHIP_TO',
    ];

    public function __construct(
        private readonly string $type,
        private readonly string $name,
        private readonly string $street,
        private readonly string $zip,
        private readonly string $city,
        private readonly string $country
    ) {
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getStreet(): string
    {
        return $this->street;
    }

    public function getZip(): string
    {
        return $this->zip;
    }

    public function getCity(): string
    {
        return $this->city;
    }

    public function getCountry(): string
    {
        return $this->country;
    }
}
