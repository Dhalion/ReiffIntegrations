<?php

declare(strict_types=1);

namespace ReiffIntegrations\Sap\Contract\ApiClient;

use ReiffIntegrations\Sap\Contract\Struct\ContractStatusStruct;

class ContractStatusResponse
{
    public function __construct(
        private readonly bool $success,
        private readonly string $rawResponse,
        private readonly ContractStatusStruct $contractStatus,
        private readonly ?string $returnMessage = null
    ) {
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getRawResponse(): string
    {
        return $this->rawResponse;
    }

    public function getReturnMessage(): ?string
    {
        return $this->returnMessage;
    }

    public function getContractStatus(): ContractStatusStruct
    {
        return $this->contractStatus;
    }
}
