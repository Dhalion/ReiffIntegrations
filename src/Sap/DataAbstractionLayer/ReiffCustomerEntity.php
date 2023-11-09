<?php

declare(strict_types=1);

namespace ReiffIntegrations\Sap\DataAbstractionLayer;

use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;

class ReiffCustomerEntity extends Entity
{
    protected ?CustomerEntity $customer  = null;
    protected ?string $customerId        = null;
    protected ?string $debtorNumber      = null;
    protected ?string $salesOrganization = null;

    public function getCustomer(): ?CustomerEntity
    {
        return $this->customer;
    }

    public function setCustomer(?CustomerEntity $customer): void
    {
        $this->customer = $customer;
    }

    public function getCustomerId(): ?string
    {
        return $this->customerId;
    }

    public function setCustomerId(string $customerId): void
    {
        $this->customerId = $customerId;
    }

    public function getDebtorNumber(): ?string
    {
        return $this->debtorNumber;
    }

    public function setDebtorNumber(?string $debtorNumber): void
    {
        $this->debtorNumber = $debtorNumber;
    }

    public function getSalesOrganization(): ?string
    {
        return $this->salesOrganization;
    }

    public function setSalesOrganization(?string $salesOrganization): void
    {
        $this->salesOrganization = $salesOrganization;
    }
}
