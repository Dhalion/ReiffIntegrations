<?php

declare(strict_types=1);

namespace ReiffIntegrations\MeDaPro\Message;

use ReiffIntegrations\MeDaPro\Struct\CatalogMetadata;
use ReiffIntegrations\MeDaPro\Struct\CatalogStruct;
use ReiffIntegrations\Util\Message\AbstractImportMessage;
use Shopware\Core\Framework\Context;

class CategoryImportMessage extends AbstractImportMessage
{
    public function __construct(
        private readonly CatalogStruct $catalogStruct,
        string $archiveFileName,
        CatalogMetadata $catalogMetadata,
        Context $context
    ) {
        parent::__construct($archiveFileName, $catalogMetadata, $context);
    }

    public function getCatalogStruct(): CatalogStruct
    {
        return $this->catalogStruct;
    }
}
