<?php

declare(strict_types=1);

namespace ReiffIntegrations\Seeburger\Struct;

use Shopware\Core\Framework\Struct\Struct;

class OrderData extends Struct
{
    public function __construct(
        protected string $orderId,
        protected string $elementId,
    ) {
    }

    public function getOrderId(): string
    {
        return $this->orderId;
    }

    public function getElementId(): string
    {
        return $this->elementId;
    }
}
