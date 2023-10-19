<?php

declare(strict_types=1);

namespace ReiffIntegrations\Sap\Struct;

use Shopware\Core\Framework\Struct\Collection;

/**
 * @method void                     add(OfferDocumentStruct $struct)
 * @method void                     set(string $key, OfferDocumentStruct $struct)
 * @method OfferDocumentStruct[]    getIterator()
 * @method OfferDocumentStruct[]    getElements()
 * @method null|OfferDocumentStruct get(string $key)
 * @method null|OfferDocumentStruct first()
 * @method null|OfferDocumentStruct last()
 */
class OfferDocumentCollection extends Collection
{
    public function getExpectedClass(): string
    {
        return OfferDocumentStruct::class;
    }

    public function getOfferByNumber(string $offerNumber): ?OfferDocumentStruct
    {
        foreach ($this->getElements() as $offer) {
            if ($offer->getNumber() === $offerNumber) {
                return $offer;
            }
        }

        return null;
    }
}
