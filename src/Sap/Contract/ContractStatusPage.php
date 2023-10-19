<?php

declare(strict_types=1);

namespace ReiffIntegrations\Sap\Contract;

use ReiffIntegrations\Sap\Contract\Struct\ContractStatusStruct;
use Shopware\Storefront\Page\Page;

class ContractStatusPage extends Page
{
    protected ?ContractStatusStruct $contractStatus = null;
    protected bool $success                         = false;

    public function getContractStatus(): ?ContractStatusStruct
    {
        return $this->contractStatus;
    }

    public function setContractStatus(?ContractStatusStruct $contractStatus): void
    {
        $this->contractStatus = $contractStatus;
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function setSuccess(bool $success): void
    {
        $this->success = $success;
    }
}
