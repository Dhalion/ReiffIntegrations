<?php

declare(strict_types=1);

namespace ReiffIntegrations\Sap\CustomOrderNumber\Message;

use ReiffIntegrations\Sap\CustomOrderNumber\Struct\OrderNumberUpdateStruct;
use ReiffIntegrations\Util\Message\AbstractImportMessage;
use Shopware\Core\Framework\Context;

class OrderNumberUpdateMessage
{
    public function __construct(
        private readonly OrderNumberUpdateStruct $updateStruct,
        private readonly Context $context,
    ) {
    }

    public function getUpdateStruct(): OrderNumberUpdateStruct
    {
        return $this->updateStruct;
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
