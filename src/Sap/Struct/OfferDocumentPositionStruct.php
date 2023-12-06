<?php

declare(strict_types=1);

namespace ReiffIntegrations\Sap\Struct;

use Shopware\Core\Framework\Struct\Struct;

class OfferDocumentPositionStruct extends Struct
{
    private ?string $number;
    private ?string $itemNumber;
    private ?string $description;
    private ?float $orderQuantity;
    private ?string $orderUom;
    private ?float $itemPrice;
    private ?float $priceUnit;
    private ?string $priceUom;
    private ?float $itemValue;
    private ?string $currency;
    private ?int $numerator;
    private ?string $numeratorUom;
    private ?int $denominator;
    private ?string $denominatorUom;

    public function __construct(
        string $number = null,
        string $itemNumber = null,
        string $description = null,
        float $orderQuantity = null,
        string $orderUom = null,
        float $itemPrice = null,
        float $priceUnit = null,
        string $priceUom = null,
        float $itemValue = null,
        string $currency = null,
        int $numerator = null,
        string $numeratorUom = null,
        int $denominator = null,
        string $denominatorUom = null
    ) {
        $this->number         = $number;
        $this->itemNumber     = $itemNumber;
        $this->description    = $description;
        $this->orderQuantity  = $orderQuantity;
        $this->orderUom       = $orderUom;
        $this->itemPrice      = $itemPrice;
        $this->priceUnit      = $priceUnit;
        $this->priceUom       = $priceUom;
        $this->itemValue      = $itemValue;
        $this->currency       = $currency;
        $this->numerator      = $numerator;
        $this->numeratorUom   = $numeratorUom;
        $this->denominator    = $denominator;
        $this->denominatorUom = $denominatorUom;
    }

    public function getNumber(): ?string
    {
        return $this->number;
    }

    public function getItemNumber(): ?string
    {
        return $this->itemNumber;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getOrderQuantity(): ?float
    {
        return $this->orderQuantity;
    }

    public function getOrderUom(): ?string
    {
        return $this->orderUom;
    }

    public function getItemPrice(): ?float
    {
        return $this->itemPrice;
    }

    public function getPriceUnit(): ?float
    {
        return $this->priceUnit;
    }

    public function getPriceUom(): ?string
    {
        return $this->priceUom;
    }

    public function getItemValue(): ?float
    {
        return $this->itemValue;
    }

    public function getCurrency(): ?string
    {
        return $this->currency;
    }

    public function getNumerator(): ?int
    {
        return $this->numerator;
    }

    public function getNumeratorUom(): ?string
    {
        return $this->numeratorUom;
    }

    public function getDenominator(): ?int
    {
        return $this->denominator;
    }

    public function getDenominatorUom(): ?string
    {
        return $this->denominatorUom;
    }
}
