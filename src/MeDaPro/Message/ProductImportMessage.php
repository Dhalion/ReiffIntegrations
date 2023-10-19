<?php

declare(strict_types=1);

namespace ReiffIntegrations\MeDaPro\Message;

use ReiffIntegrations\MeDaPro\Struct\ProductStruct;
use ReiffIntegrations\Util\Message\AbstractImportMessage;
use Shopware\Core\Framework\Context;

class ProductImportMessage extends AbstractImportMessage
{
    public function __construct(
        private readonly ProductStruct $product,
        string $archiveFileName,
        Context $context
    ) {
        parent::__construct($archiveFileName, $context);
    }

    public function getProduct(): ProductStruct
    {
        return $this->product;
    }
}
