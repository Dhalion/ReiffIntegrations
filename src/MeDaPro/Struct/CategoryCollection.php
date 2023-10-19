<?php

declare(strict_types=1);

namespace ReiffIntegrations\MeDaPro\Struct;

use Shopware\Core\Framework\Struct\Collection;

/**
 * @method void                add(CategoryStruct $struct)
 * @method void                set(string $key, CategoryStruct $struct)
 * @method CategoryStruct[]    getIterator()
 * @method CategoryStruct[]    getElements()
 * @method null|CategoryStruct get(string $key)
 * @method null|CategoryStruct first()
 * @method null|CategoryStruct last()
 */
class CategoryCollection extends Collection
{
    public function getExpectedClass(): string
    {
        return CategoryStruct::class;
    }
}
