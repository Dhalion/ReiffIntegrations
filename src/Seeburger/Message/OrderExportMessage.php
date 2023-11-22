<?php

declare(strict_types=1);

namespace ReiffIntegrations\Seeburger\Message;

use ReiffIntegrations\Seeburger\Struct\OrderData;
use ReiffIntegrations\Util\Message\AbstractExportMessage;
use Shopware\Core\Framework\Context;

class OrderExportMessage extends AbstractExportMessage
{
    public function __construct(
        private readonly OrderData $orderData,
        Context $context
    ) {
        parent::__construct($context);
    }

    public function getOrderData(): OrderData
    {
        return $this->orderData;
    }
}
