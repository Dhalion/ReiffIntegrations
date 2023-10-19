<?php

declare(strict_types=1);

namespace ReiffIntegrations\Sap\Api\Client\Orders;

use ReiffIntegrations\Sap\Struct\OrderDetailStruct;

class OrderDetailApiResponse
{
    public function __construct(
        private readonly bool $success,
        private readonly string $rawResponse,
        private readonly ?OrderDetailStruct $order,
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

    public function getOrder(): ?OrderDetailStruct
    {
        return $this->order;
    }
}
