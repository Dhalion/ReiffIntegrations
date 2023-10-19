<?php

declare(strict_types=1);

namespace ReiffIntegrations\Sap\Contract\ApiClient;

use ReiffIntegrations\Sap\Contract\Struct\ContractListCollection;

class ContractListResponse
{
    public function __construct(
        private readonly bool $success,
        private readonly string $rawResponse,
        private readonly ContractListCollection $contracts,
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

    public function getContracts(): ContractListCollection
    {
        return $this->contracts;
    }
}
