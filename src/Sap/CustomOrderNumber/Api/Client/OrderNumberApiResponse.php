<?php

declare(strict_types=1);

namespace ReiffIntegrations\Sap\CustomOrderNumber\Api\Client;

use ReiffIntegrations\Sap\CustomOrderNumber\Struct\OrderNumberCollection;

class OrderNumberApiResponse
{
    public function __construct(
        private readonly bool $success,
        private readonly string $rawResponse,
        private readonly OrderNumberCollection $documents,
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

    public function getDocuments(): OrderNumberCollection
    {
        return $this->documents;
    }
}
