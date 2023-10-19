<?php

declare(strict_types=1);

namespace ReiffIntegrations\Seeburger\Struct;

use Shopware\Core\Framework\Struct\Struct;

class OrderId extends Struct
{
    public function __construct(
        protected string $orderId
    ) {
    }

    public function getOrderId(): string
    {
        return $this->orderId;
    }
}
