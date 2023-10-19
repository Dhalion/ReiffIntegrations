<?php

declare(strict_types=1);

namespace ReiffIntegrations\Sap\Struct;

use Shopware\Core\Framework\Struct\Struct;

class OrderDetailStruct extends Struct
{
    public function __construct(
        private readonly ?string $number,
        private readonly ?string $reference,
        private readonly ?\DateTimeImmutable $orderDate,
        private readonly ?string $customer,
        private readonly ?string $debtorNumber,
        private readonly ?string $status,
        private readonly ?float $netTotal,
        private readonly ?string $currency,
        private readonly ?float $shippingCost,
        private readonly ?float $extraCost,
        private readonly OrderAddressCollection $addresses,
        private readonly OrderLineItemCollection $lineItems,
        private readonly OrderDocumentCollection $documents,
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

    public function getDebtorNumber(): ?string
    {
        return $this->debtorNumber;
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

    public function getShippingCost(): ?float
    {
        return $this->shippingCost;
    }

    public function getExtraCost(): ?float
    {
        return $this->extraCost;
    }

    public function getAddresses(): OrderAddressCollection
    {
        return $this->addresses;
    }

    public function getLineItems(): OrderLineItemCollection
    {
        return $this->lineItems;
    }

    public function getDocuments(): OrderDocumentCollection
    {
        return $this->documents;
    }
}
