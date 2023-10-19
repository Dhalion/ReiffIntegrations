<?php

declare(strict_types=1);

namespace ReiffIntegrations\Sap\DeliveryInformation\Struct;

use Shopware\Core\Framework\Struct\Struct;

class AvailabilityStruct extends Struct
{
    public function __construct(
        protected readonly string $productNumber,
        protected readonly string $plant,
        protected readonly float $quantity,
        protected readonly string $uom,
        protected readonly int $code,
        protected ?string $translatedResult = null
    ) {
    }

    public function getProductNumber(): string
    {
        return $this->productNumber;
    }

    public function getPlant(): string
    {
        return $this->plant;
    }

    public function getQuantity(): float
    {
        return $this->quantity;
    }

    public function getUom(): string
    {
        return $this->uom;
    }

    public function getCode(): int
    {
        return $this->code;
    }

    public function getTranslatedResult(): ?string
    {
        return $this->translatedResult;
    }

    public function setTranslatedResult(?string $translatedResult): void
    {
        $this->translatedResult = $translatedResult;
    }
}
