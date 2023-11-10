<?php

declare(strict_types=1);

namespace ReiffIntegrations\Sap\CustomOrderNumber\Struct;

use Shopware\Core\Framework\Struct\Struct;

class OrderNumberUpdateStruct extends Struct
{
    public function __construct(
        protected string $debtorNumber,
        protected string $customerId,
        protected string $salesOrganization,
    ) {
    }

    public function getDebtorNumber(): string
    {
        return $this->debtorNumber;
    }

    public function getCustomerId(): string
    {
        return $this->customerId;
    }

    public function getSalesOrganization(): string
    {
        return $this->salesOrganization;
    }
}
