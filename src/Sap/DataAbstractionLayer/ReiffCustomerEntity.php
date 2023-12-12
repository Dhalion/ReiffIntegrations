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
    protected ?string $salesOrganisation = null;

    public function getCustomer(): ?CustomerEntity
    {
        return $this->customer;
    }

    public function getCustomerId(): ?string
    {
        return $this->customerId;
    }

    public function getDebtorNumber(): ?string
    {
        return $this->debtorNumber;
    }

    public function getSalesOrganisation(): ?string
    {
        return $this->salesOrganisation;
    }

    public function hasIncompleteFields(): bool
    {
        if (empty($this->salesOrganisation)) {
            return true;
        }

        if ($this->salesOrganisation === '-') {
            return true;
        }

        if (empty($this->debtorNumber)) {
            return true;
        }

        if ($this->debtorNumber === '-') {
            return true;
        }

        if (empty($this->customerId)) {
            return true;
        }

        return false;
    }
}
