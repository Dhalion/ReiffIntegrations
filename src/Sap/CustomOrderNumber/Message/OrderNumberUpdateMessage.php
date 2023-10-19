<?php

declare(strict_types=1);

namespace ReiffIntegrations\Sap\CustomOrderNumber\Message;

use ReiffIntegrations\Sap\CustomOrderNumber\Struct\OrderNumberUpdateStruct;
use ReiffIntegrations\Util\Message\AbstractImportMessage;
use Shopware\Core\Framework\Context;

class OrderNumberUpdateMessage extends AbstractImportMessage
{
    public function __construct(
        private readonly OrderNumberUpdateStruct $updateStruct,
        string $archiveFileName,
        Context $context,
    ) {
        parent::__construct($archiveFileName, $context);
    }

    public function getUpdateStruct(): OrderNumberUpdateStruct
    {
        return $this->updateStruct;
    }
}
