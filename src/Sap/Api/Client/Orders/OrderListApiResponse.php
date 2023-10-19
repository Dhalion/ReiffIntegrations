<?php

declare(strict_types=1);

namespace ReiffIntegrations\Sap\Api\Client\Orders;

use ReiffIntegrations\Sap\Struct\OrderCollection;

class OrderListApiResponse
{
    public function __construct(
        private readonly bool $success,
        private readonly string $rawResponse,
        private readonly OrderCollection $orders,
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

    public function getOrders(): OrderCollection
    {
        return $this->orders;
    }
}
