<?php

declare(strict_types=1);

namespace ReiffIntegrations\Seeburger\Exception;

class ProductNotFoundException extends \Exception
{
    public function __construct(?string $orderNumber, string $lineItemId, int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct(
            sprintf('Could not convert order %s to IDOC. Product data for lineitem %s is missing.', $orderNumber, $lineItemId),
            $code,
            $previous
        );
    }
}
