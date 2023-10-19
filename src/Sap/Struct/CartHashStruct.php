<?php

declare(strict_types=1);

namespace ReiffIntegrations\Sap\Struct;

use Shopware\Core\Framework\Struct\Struct;

class CartHashStruct extends Struct
{
    public const NAME = 'reiffCartHash';

    public function __construct(
        private readonly string $hash
    ) {
    }

    public function getHash(): string
    {
        return $this->hash;
    }
}
