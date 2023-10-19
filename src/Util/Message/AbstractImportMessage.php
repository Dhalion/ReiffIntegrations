<?php

declare(strict_types=1);

namespace ReiffIntegrations\Util\Message;

use Shopware\Core\Framework\Context;

abstract class AbstractImportMessage
{
    public function __construct(
        private readonly string $archiveFileName,
        private readonly Context $context,
    ) {
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getArchiveFileName(): string
    {
        return $this->archiveFileName;
    }
}
