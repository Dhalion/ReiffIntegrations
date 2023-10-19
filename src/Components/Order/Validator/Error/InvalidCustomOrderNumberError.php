<?php

declare(strict_types=1);

namespace ReiffIntegrations\Components\Order\Validator\Error;

use Shopware\Core\Checkout\Cart\Error\Error;

class InvalidCustomOrderNumberError extends Error
{
    private const KEY = 'custom-order-number-blocked';

    public function __construct(private string $customOrderNumber, private int $maxCharacters)
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
        return ['customOrderNumber' => $this->customOrderNumber, 'characters' => $this->maxCharacters];
    }
}
