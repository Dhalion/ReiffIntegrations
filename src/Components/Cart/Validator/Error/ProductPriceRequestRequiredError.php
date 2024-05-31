<?php declare(strict_types=1);

namespace ReiffIntegrations\Components\Cart\Validator\Error;

use Shopware\Core\Checkout\Cart\Error\Error;

class ProductPriceRequestRequiredError extends Error
{
    private const KEY = 'product-price-request-required-error';

    private string $lineItemId;

    public function __construct(string $lineItemId)
    {
        $this->lineItemId = $lineItemId;
        parent::__construct();
    }

    public function getId(): string
    {
        return $this->lineItemId;
    }

    public function getMessageKey(): string
    {
        return self::KEY;
    }

    public function getLevel(): int
    {
        return self::LEVEL_ERROR;
    }

    public function blockOrder(): bool
    {
        return true;
    }

    public function getParameters(): array
    {
        return ['lineItemId' => $this->lineItemId];
    }
}
