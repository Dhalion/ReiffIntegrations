<?php

declare(strict_types=1);

namespace ReiffIntegrations\Util\Message;

use Shopware\Core\Framework\Context;

abstract class AbstractExportMessage
{
    private Context $context;

    public function __construct(Context $context)
    {
        $this->context = $context;
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
