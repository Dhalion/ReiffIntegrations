<?php

declare(strict_types=1);

namespace ReiffIntegrations\MeDaPro\Struct;

use Shopware\Core\Framework\Struct\Collection;

/**
 * @method void               add(ProductStruct $struct)
 * @method void               set(string $key, ProductStruct $struct)
 * @method ProductStruct[]    getIterator()
 * @method ProductStruct[]    getElements()
 * @method null|ProductStruct get(string $key)
 * @method null|ProductStruct first()
 * @method null|ProductStruct last()
 */
class ProductCollection extends Collection
{
    public function getExpectedClass(): string
    {
        return ProductStruct::class;
    }
}
