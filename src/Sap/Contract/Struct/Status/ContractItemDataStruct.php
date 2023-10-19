<?php

declare(strict_types=1);

namespace ReiffIntegrations\Sap\Contract\Struct\Status;

use Shopware\Core\Framework\Struct\Struct;

class ContractItemDataStruct extends Struct
{
    private ?string $productId           = null;
    private ?string $customProductNumber = null;

    public function __construct(
        private readonly ?string $itemNumber,
        private readonly ?string $materialNumber,
        private readonly ?string $customerMaterialNumber,
        private readonly ?string $shortText,
        private readonly ?float $targetQuantity,
        private readonly ?float $usedQuantity,
        private readonly ?float $openQuantity,
        private ?string $salesUnit,
        private readonly ?string $salesUnitIso,
        private readonly ?float $netPrice,
        private readonly ?float $netValue,
        private readonly ?string $currency,
        private readonly ?string $currencyIso,
        private readonly ?string $statusDescription,
        private readonly ?ContractItemUsageCollection $itemUsage
    ) {
    }

    public function getItemNumber(): ?string
    {
        return $this->itemNumber;
    }

    public function getMaterialNumber(): ?string
    {
        return $this->materialNumber;
    }

    public function getCustomerMaterialNumber(): ?string
    {
        return $this->customerMaterialNumber;
    }

    public function getShortText(): ?string
    {
        return $this->shortText;
    }

    public function getTargetQuantity(): ?float
    {
        return $this->targetQuantity;
    }

    public function getUsedQuantity(): ?float
    {
        return $this->usedQuantity;
    }

    public function getOpenQuantity(): ?float
    {
        return $this->openQuantity;
    }

    public function setSalesUnit(?string $salesUnit): void
    {
        $this->salesUnit = $salesUnit;
    }

    public function getSalesUnit(): ?string
    {
        return $this->salesUnit;
    }

    public function getSalesUnitIso(): ?string
    {
        return $this->salesUnitIso;
    }

    public function getNetPrice(): ?float
    {
        return $this->netPrice;
    }

    public function getNetValue(): ?float
    {
        return $this->netValue;
    }

    public function getCurrency(): ?string
    {
        return $this->currency;
    }

    public function getCurrencyIso(): ?string
    {
        return $this->currencyIso;
    }

    public function getStatusDescription(): ?string
    {
        return $this->statusDescription;
    }

    public function getItemUsage(): ?ContractItemUsageCollection
    {
        return $this->itemUsage;
    }

    public function getProductId(): ?string
    {
        return $this->productId;
    }

    public function setProductId(?string $productId): void
    {
        $this->productId = $productId;
    }

    public function getCustomProductNumber(): ?string
    {
        return $this->customProductNumber;
    }

    public function setCustomProductNumber(?string $customProductNumber): void
    {
        $this->customProductNumber = $customProductNumber;
    }
}
