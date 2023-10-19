<?php

declare(strict_types=1);

namespace ReiffIntegrations\Components\Order\Validator\Error;

use Shopware\Core\Checkout\Cart\Error\Error;

class InvalidCommissionProductError extends Error
{
    private const KEY = 'commission-product-blocked';

    public function __construct(private string $itemKey, private string $commissionText, private int $maxCharacters)
    {
        parent::__construct();
    }

    public function getId(): string
    {
        return self::KEY . '-' . $this->itemKey;
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
        return ['commissionText' => $this->commissionText, 'characters' => $this->maxCharacters];
    }
}
