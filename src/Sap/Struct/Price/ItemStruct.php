<?php

declare(strict_types=1);

namespace ReiffIntegrations\Sap\Struct\Price;

use Shopware\Core\Framework\Struct\Struct;

class ItemStruct extends Struct
{
    public const ORDER_UNIT_SHIPPING = 'ship';

    public function __construct(
        private readonly string $productNumber,
        private readonly int $quantity,
        private readonly float $totalPrice,
        private readonly int $priceQuantity,
        private readonly string $orderUnit
    ) {
    }

    public function getProductNumber(): string
    {
        return $this->productNumber;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function getTotalPrice(): float
    {
        return $this->totalPrice;
    }

    public function getPriceQuantity(): int
    {
        return $this->priceQuantity;
    }

    public function getOrderUnit(): string
    {
        return $this->orderUnit;
    }
}
