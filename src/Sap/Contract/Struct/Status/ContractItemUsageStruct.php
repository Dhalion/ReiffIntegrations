<?php

declare(strict_types=1);

namespace ReiffIntegrations\Sap\Contract\Struct\Status;

use Shopware\Core\Framework\Struct\Struct;

class ContractItemUsageStruct extends Struct
{
    public function __construct(
        private readonly ?string $orderNumber,
        private readonly ?string $orderItemNumber,
        private readonly ?\DateTimeImmutable $orderDate,
        private readonly ?string $orderCustReference,
        private readonly ?\DateTimeImmutable $orderCustDate,
        private readonly ?float $orderItemQuantity,
        private ?string $orderItemUom
    ) {
    }

    public function getOrderNumber(): ?string
    {
        return $this->orderNumber;
    }

    public function getOrderItemNumber(): ?string
    {
        return $this->orderItemNumber;
    }

    public function getOrderDate(): ?\DateTimeImmutable
    {
        return $this->orderDate;
    }

    public function getOrderCustReference(): ?string
    {
        return $this->orderCustReference;
    }

    public function getOrderCustDate(): ?\DateTimeImmutable
    {
        return $this->orderCustDate;
    }

    public function getOrderItemQuantity(): ?float
    {
        return $this->orderItemQuantity;
    }

    public function setOrderItemUom(?string $orderItemUom): void
    {
        $this->orderItemUom = $orderItemUom;
    }

    public function getOrderItemUom(): ?string
    {
        return $this->orderItemUom;
    }
}
