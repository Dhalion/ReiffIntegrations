<?php

declare(strict_types=1);

namespace ReiffIntegrations\Seeburger\Message;

use ReiffIntegrations\Seeburger\Struct\OrderId;
use ReiffIntegrations\Util\Message\AbstractExportMessage;
use Shopware\Core\Framework\Context;

class OrderExportMessage extends AbstractExportMessage
{
    private OrderId $orderId;

    public function __construct(OrderId $orderId, Context $context)
    {
        parent::__construct($context);

        $this->orderId = $orderId;
    }

    public function getOrderId(): OrderId
    {
        return $this->orderId;
    }
}
