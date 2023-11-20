<?php

declare(strict_types=1);

namespace ReiffIntegrations\Seeburger\Message;

use ReiffIntegrations\Seeburger\Struct\OrderData;
use ReiffIntegrations\Util\Message\AbstractExportMessage;
use Shopware\Core\Framework\Context;

class OrderExportMessage extends AbstractExportMessage
{
    private OrderData $orderData;

    public function __construct(OrderData $orderData, Context $context)
    {
        parent::__construct($context);

        $this->orderData = $orderData;
    }

    public function getOrderData(): OrderData
    {
        return $this->orderData;
    }
}
