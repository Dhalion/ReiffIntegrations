<?php

declare(strict_types=1);

namespace ReiffIntegrations\Sap\Offer\Page;

use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Storefront\Page\Page;

class AccountOfferPage extends Page
{
    protected CustomerEntity $customer;

    public function getCustomer(): CustomerEntity
    {
        return $this->customer;
    }

    public function setCustomer(CustomerEntity $customer): void
    {
        $this->customer = $customer;
    }
}
