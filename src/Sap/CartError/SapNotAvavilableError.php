<?php declare(strict_types=1);

namespace ReiffIntegrations\Sap\CartError;

use Shopware\Core\Checkout\Cart\Error\Error;
use Shopware\Core\Framework\Log\Package;

class SapNotAvavilableError extends Error
{
    private const KEY = 'sap-not-available';

    public function __construct()
    {
        $this->message = 'SAP not available';

        parent::__construct($this->message);
    }

    public function isPersistent(): bool
    {
        return true;
    }

    public function getParameters(): array
    {
        return [];
    }

    public function blockOrder(): bool
    {
        return true;
    }

    public function getId(): string
    {
        return self::KEY;
    }

    public function getLevel(): int
    {
        return self::LEVEL_WARNING;
    }

    public function getMessageKey(): string
    {
        return self::KEY;
    }
}
