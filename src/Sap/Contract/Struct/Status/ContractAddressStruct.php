<?php

declare(strict_types=1);

namespace ReiffIntegrations\Sap\Contract\Struct\Status;

use Shopware\Core\Framework\Struct\Struct;

class ContractAddressStruct extends Struct
{
    public function __construct(
        private readonly ?string $type,
        private readonly ?string $name,
        private readonly ?string $name2,
        private readonly ?string $street,
        private readonly ?string $number,
        private readonly ?string $zip,
        private readonly ?string $city,
        private readonly ?string $country,
        private readonly ?string $phone,
        private readonly ?string $teleBox,
        private readonly ?string $fax
    ) {
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getName2(): ?string
    {
        return $this->name2;
    }

    public function getStreet(): ?string
    {
        return $this->street;
    }

    public function getNumber(): ?string
    {
        return $this->number;
    }

    public function getZip(): ?string
    {
        return $this->zip;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function getCountry(): ?string
    {
        return $this->country;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function getTeleBox(): ?string
    {
        return $this->teleBox;
    }

    public function getFax(): ?string
    {
        return $this->fax;
    }
}
