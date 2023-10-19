<?php

declare(strict_types=1);

namespace ReiffIntegrations\Seeburger\Struct;

use Shopware\Core\Framework\Struct\Collection;

/**
 * @method void         add(IdocRow $entity)
 * @method void         set(string $key, IdocRow $entity)
 * @method IdocRow[]    getIterator()
 * @method IdocRow[]    getElements()
 * @method null|IdocRow get(string $key)
 * @method null|IdocRow first()
 * @method null|IdocRow last()
 */
class IdocRowCollection extends Collection
{
    public function getExpectedClass(): string
    {
        return IdocRow::class;
    }

    public function validate(): void
    {
        foreach ($this->getElements() as $idocRow) {
            $idocRow->validate();
        }
    }
}
