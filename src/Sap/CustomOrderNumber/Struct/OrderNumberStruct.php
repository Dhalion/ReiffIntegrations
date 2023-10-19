<?php

declare(strict_types=1);

namespace ReiffIntegrations\Sap\CustomOrderNumber\Struct;

use Shopware\Core\Framework\Struct\Struct;

class OrderNumberStruct extends Struct
{
    public function __construct(
        private readonly string $sapNumber,
        private readonly string $customerNumber
    ) {
    }

    public function getSapNumber(): string
    {
        return $this->sapNumber;
    }

    public function getCustomerNumber(): string
    {
        return $this->customerNumber;
    }
}
