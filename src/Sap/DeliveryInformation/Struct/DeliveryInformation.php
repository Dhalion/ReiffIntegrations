<?php

declare(strict_types=1);

namespace ReiffIntegrations\Sap\DeliveryInformation\Struct;

use Shopware\Core\Framework\Struct\Struct;

class DeliveryInformation extends Struct
{
    public const NAME = 'k10r_reiff_integrations_delivery_information';

    public function __construct(protected ?int $deliveryCode, protected bool $isProductShippingFree, protected bool $isProductActive)
    {
    }

    public function getDeliveryCode(): ?int
    {
        return $this->deliveryCode;
    }

    public function setDeliveryCode(?int $deliveryCode): void
    {
        $this->deliveryCode = $deliveryCode;
    }

    public function isProductShippingFree(): bool
    {
        return $this->isProductShippingFree;
    }

    public function setIsProductShippingFree(bool $isProductShippingFree): void
    {
        $this->isProductShippingFree = $isProductShippingFree;
    }

    public function isProductActive(): bool
    {
        return $this->isProductActive;
    }

    public function setIsProductActive(bool $isProductActive): void
    {
        $this->isProductActive = $isProductActive;
    }
}
