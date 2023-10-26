<?php

declare(strict_types=1);

namespace ReiffIntegrations\Sap\Struct;

use Shopware\Core\Framework\Struct\Struct;

class OrderLineItemStruct extends Struct
{
    public function __construct(
        private readonly ?string $productNumber,
        private readonly ?string $customerProductNumber,
        private readonly ?string $name,
        private ?string $unit,
        private readonly ?float $quantity,
        private readonly ?float $netTotal,
        private readonly ?string $productId
    ) {
    }

    public function getProductNumber(): ?string
    {
        return $this->productNumber;
    }

    public function getCustomerProductNumber(): ?string
    {
        return $this->customerProductNumber;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setUnit(?string $unit): void
    {
        $this->unit = $unit;
    }

    public function getUnit(): ?string
    {
        return $this->unit;
    }

    public function getQuantity(): ?float
    {
        return $this->quantity;
    }

    public function getNetTotal(): ?float
    {
        return $this->netTotal;
    }

    public function getProductId(): ?string
    {
        return $this->getProductId();
    }
}
