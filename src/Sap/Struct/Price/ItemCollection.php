<?php

declare(strict_types=1);

namespace ReiffIntegrations\Sap\Struct\Price;

use Shopware\Core\Framework\Struct\Collection;

/**
 * @method void            add(ItemStruct $struct)
 * @method void            set(string $key, ItemStruct $struct)
 * @method ItemStruct[]    getIterator()
 * @method ItemStruct[]    getElements()
 * @method null|ItemStruct get(string $key)
 * @method null|ItemStruct first()
 * @method null|ItemStruct last()
 */
class ItemCollection extends Collection
{
    public const ITEM_KEY_HANDLE = '%s - %s';

    public function getExpectedClass(): string
    {
        return ItemStruct::class;
    }

    public function getItemsByNumber(string $productNumber): self
    {
        return $this->createNew(
            $this->filter(static function (ItemStruct $item) use ($productNumber) {
                return $productNumber === $item->getProductNumber();
            })
        );
    }

    public function getLowestPrice(): ?ItemStruct
    {
        $newCollection = $this->createNew($this->elements);
        $newCollection->sort(static function (ItemStruct $a, ItemStruct $b) {
            return $a->getTotalPrice() <=> $b->getTotalPrice();
        });

        return $newCollection->first();
    }
}
