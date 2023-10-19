<?php

declare(strict_types=1);

namespace ReiffIntegrations\Seeburger\Provider;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;

class OrderProvider
{
    public static function setupCriteria(Criteria $criteria): void
    {
        $criteria->addAssociation('currency');
        $criteria->addAssociation('salesChannel');
        $criteria->addAssociation('language');
        $criteria->addAssociation('orderCustomer.customer.group');
        $criteria->addAssociation('transactions.paymentMethod.translations');
        $criteria->addAssociation('transactions.stateMachineState');
        $criteria->addAssociation('deliveries.shippingMethod.translations');
        $criteria->addAssociation('deliveries.shippingOrderAddress.salutation.translations');
        $criteria->addAssociation('deliveries.shippingOrderAddress.country');
        $criteria->addAssociation('lineItems.product.unit');
        $criteria->addAssociation('addresses.salutation.translations');
        $criteria->addAssociation('addresses.country');
        $criteria->addAssociation('billingAddress.salutation.translations');
        $criteria->addAssociation('billingAddress.country');
        $criteria->addAssociation('stateMachineState');

        $criteria->addSorting(new FieldSorting('lineItems.position'));
    }
}
