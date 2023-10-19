<?php

declare(strict_types=1);

namespace ReiffIntegrations\Sap\Struct;

use Shopware\Core\Framework\Struct\Collection;

/**
 * @method void                             add(OfferDocumentPositionStruct $struct)
 * @method void                             set(string $key, OfferDocumentPositionStruct $struct)
 * @method OfferDocumentPositionStruct[]    getIterator()
 * @method OfferDocumentPositionStruct[]    getElements()
 * @method null|OfferDocumentPositionStruct get(string $key)
 * @method null|OfferDocumentPositionStruct first()
 * @method null|OfferDocumentPositionStruct last()
 */
class OfferDocumentPositionCollection extends Collection
{
    public function getExpectedClass(): string
    {
        return OfferDocumentPositionStruct::class;
    }
}
