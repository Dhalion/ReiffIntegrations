<?php

declare(strict_types=1);

namespace ReiffIntegrations\Sap\Page\Orders;

use ReiffIntegrations\Sap\Struct\OrderDetailStruct;
use Shopware\Storefront\Page\Page;

class OrderDetailPage extends Page
{
    private ?OrderDetailStruct $order = null;

    public function getOrder(): ?OrderDetailStruct
    {
        return $this->order;
    }

    public function setOrder(?OrderDetailStruct $order): void
    {
        $this->order = $order;
    }
}
