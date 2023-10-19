<?php

declare(strict_types=1);

namespace ReiffIntegrations\Sap\Struct;

use Shopware\Core\Framework\Struct\Struct;

class OrderStruct extends Struct
{
    public function __construct(
        private readonly ?string $number,
        private readonly ?string $reference,
        private readonly ?\DateTimeImmutable $orderDate,
        private readonly ?string $customer,
        private readonly ?string $status,
        private readonly ?float $netTotal,
        private readonly ?string $currency
    ) {
    }

    public function getNumber(): ?string
    {
        return $this->number;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function getOrderDate(): ?\DateTimeImmutable
    {
        return $this->orderDate;
    }

    public function getCustomer(): ?string
    {
        return $this->customer;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function getNetTotal(): ?float
    {
        return $this->netTotal;
    }

    public function getCurrency(): ?string
    {
        return $this->currency;
    }
}
