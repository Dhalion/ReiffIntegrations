<?php

declare(strict_types=1);

namespace ReiffIntegrations\Sap\DataAbstractionLayer;

use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;

class ReiffCustomerEntity extends Entity
{
    protected ?CustomerEntity $customer = null;
    protected ?string $customerId       = null;
    protected ?string $debtorNumber     = null;
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
        return '1004';
    }
}
