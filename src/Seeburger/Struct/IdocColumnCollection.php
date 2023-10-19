<?php

declare(strict_types=1);

namespace ReiffIntegrations\Seeburger\Struct;

use Shopware\Core\Framework\Struct\Collection;

/**
 * @method void            add(IdocColumn $entity)
 * @method void            set(string $key, IdocColumn $entity)
 * @method IdocColumn[]    getIterator()
 * @method IdocColumn[]    getElements()
 * @method null|IdocColumn get(string $key)
 * @method null|IdocColumn first()
 * @method null|IdocColumn last()
 */
class IdocColumnCollection extends Collection
{
    public function getExpectedClass(): string
    {
        return IdocColumn::class;
    }
}
