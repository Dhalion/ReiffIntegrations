<?php

declare(strict_types=1);

namespace ReiffIntegrations\Seeburger\Struct;

use Shopware\Core\Framework\Struct\Struct;

class IdocColumn extends Struct
{
    private string $name;
    private int $length;
    private string $value;

    public function __construct(string $name, int $length, string $value)
    {
        $this->name   = $name;
        $this->length = $length;
        $this->value  = $value;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getLength(): int
    {
        return $this->length;
    }

    public function getValue(): string
    {
        return $this->value;
    }
}
