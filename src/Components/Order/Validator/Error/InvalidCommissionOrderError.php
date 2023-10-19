<?php

declare(strict_types=1);

namespace ReiffIntegrations\Components\Order\Validator\Error;

use Shopware\Core\Checkout\Cart\Error\Error;

class InvalidCommissionOrderError extends Error
{
    private const KEY = 'commission-order-blocked';

    public function __construct(private string $commissionText, private int $maxCharacters)
    {
        parent::__construct();
    }

    public function getId(): string
    {
        return $this->getMessageKey();
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
