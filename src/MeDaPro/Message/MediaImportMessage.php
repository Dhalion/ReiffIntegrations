<?php

declare(strict_types=1);

namespace ReiffIntegrations\MeDaPro\Message;

use ReiffIntegrations\MeDaPro\Struct\ProductsStruct;
use ReiffIntegrations\Util\Message\AbstractImportMessage;
use Shopware\Core\Framework\Context;

class MediaImportMessage extends AbstractImportMessage
{
    public function __construct(
        private readonly ProductsStruct $productsStruct,
        string $archiveFileName,
        Context $context
    ) {
        parent::__construct($archiveFileName, $context);
    }

    public function getProductsStruct(): ProductsStruct
    {
        return $this->productsStruct;
    }
}
