<?php

declare(strict_types=1);

namespace ReiffIntegrations\Seeburger\Command\Context;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Struct\Struct;

class ExportCommandContext extends Struct
{
    protected bool $isDebug;
    protected bool $isDryRun;
    protected ?int $limit = null;
    protected Context $context;

    public function __construct(bool $isDebug, bool $isDryRun, ?int $limit, Context $context)
    {
        $this->isDebug  = $isDebug;
        $this->isDryRun = $isDryRun;
        $this->limit    = $limit;
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

    public function getLimit(): ?int
    {
        return $this->limit;
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
