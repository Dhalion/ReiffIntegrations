<?php

declare(strict_types=1);

namespace ReiffIntegrations\Sap\Contract\Struct;

use ReiffIntegrations\Sap\Contract\Struct\Status\ContractAddressCollection;
use ReiffIntegrations\Sap\Contract\Struct\Status\ContractHeaderDataStruct;
use ReiffIntegrations\Sap\Contract\Struct\Status\ContractItemDataCollection;
use Shopware\Core\Framework\Struct\Struct;

class ContractStatusStruct extends Struct
{
    public function __construct(
        private readonly ?string $contractNumber = null,
        private readonly ?string $contractType = null,
        private readonly ?ContractHeaderDataStruct $headerDataStruct = null,
        private readonly ?ContractAddressCollection $addressCollection = null,
        private readonly ?ContractItemDataCollection $itemDataCollection = null
    ) {
    }

    public function getContractNumber(): ?string
    {
        return $this->contractNumber;
    }

    public function getContractType(): ?string
    {
        return $this->contractType;
    }

    public function getHeaderDataStruct(): ?ContractHeaderDataStruct
    {
        return $this->headerDataStruct;
    }

    public function getAddressCollection(): ?ContractAddressCollection
    {
        return $this->addressCollection;
    }

    public function getItemDataCollection(): ?ContractItemDataCollection
    {
        return $this->itemDataCollection;
    }
}
