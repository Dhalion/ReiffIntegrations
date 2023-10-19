<?php

declare(strict_types=1);

namespace ReiffIntegrations\Sap\Struct;

use Shopware\Core\Framework\Struct\Struct;

class OfferDocumentStruct extends Struct
{
    private ?string $number;
    private ?string $reference;
    private ?\DateTimeImmutable $validTo;
    private ?string $type;
    private ?float $orderFee;
    private ?float $additionalCosts;
    private ?string $currency;
    private OfferDocumentPositionCollection $positions;

    public function __construct(
        OfferDocumentPositionCollection $positions,
        ?string $number = null,
        ?string $reference = null,
        ?\DateTimeImmutable $validTo = null,
        ?string $type = null,
        ?float $orderFee = null,
        ?float $additionalCosts = null,
        ?string $currency = null
    ) {
        $this->positions       = $positions;
        $this->number          = $number;
        $this->reference       = $reference;
        $this->validTo         = $validTo;
        $this->type            = $type;
        $this->orderFee        = $orderFee;
        $this->additionalCosts = $additionalCosts;
        $this->currency        = $currency;
    }

    public function getPositions(): OfferDocumentPositionCollection
    {
        return $this->positions;
    }

    public function getNumber(): ?string
    {
        return $this->number;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function getValidTo(): ?\DateTimeImmutable
    {
        return $this->validTo;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function getOrderFee(): ?float
    {
        return $this->orderFee;
    }

    public function getAdditionalCosts(): ?float
    {
        return $this->additionalCosts;
    }

    public function getCurrency(): ?string
    {
        return $this->currency;
    }
}
