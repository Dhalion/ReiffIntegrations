<?php

declare(strict_types=1);

namespace ReiffIntegrations\Seeburger\Exception;

class InvalidDeliveryDateException extends \Exception
{
    public function __construct(string $orderNumber, string $dateString, int $code = 0, \Throwable $previous = null)
    {
        parent::__construct(
            sprintf('Order %s contains an invalid delivery date: %s', $orderNumber, $dateString),
            $code,
            $previous
        );
    }
}
