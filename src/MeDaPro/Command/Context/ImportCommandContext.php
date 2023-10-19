<?php

declare(strict_types=1);

namespace ReiffIntegrations\MeDaPro\Command\Context;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Struct\Struct;

class ImportCommandContext extends Struct
{
    protected bool $isDebug;
    protected bool $isDryRun;
    protected Context $context;
    private bool $force;

    public function __construct(bool $isDebug, bool $isDryRun, bool $force, Context $context)
    {
        $this->isDebug  = $isDebug;
        $this->isDryRun = $isDryRun;
        $this->force    = $force;
        $this->context  = $context;
    }

    public function isDebug(): bool
    {
        return $this->isDebug;
    }

    public function isDryRun(): bool
    {
        return $this->isDryRun;
    }

    public function isForce(): bool
    {
        return $this->force;
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
