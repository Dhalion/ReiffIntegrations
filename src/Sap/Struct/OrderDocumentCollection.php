<?php

declare(strict_types=1);

namespace ReiffIntegrations\Sap\Struct;

use Shopware\Core\Framework\Struct\Collection;

/**
 * @method void                     add(OrderDocumentStruct $struct)
 * @method void                     set(string $key, OrderDocumentStruct $struct)
 * @method OrderDocumentStruct[]    getIterator()
 * @method OrderDocumentStruct[]    getElements()
 * @method null|OrderDocumentStruct get(string $key)
 * @method null|OrderDocumentStruct first()
 * @method null|OrderDocumentStruct last()
 */
class OrderDocumentCollection extends Collection
{
    public const DOCUMENT_TYPE_INVOICE  = 'invoice';
    public const DOCUMENT_TYPE_DELIVERY = 'delivery';

    public function getExpectedClass(): string
    {
        return OrderDocumentStruct::class;
    }

    public function filterByType(string $type): self
    {
        return $this->filter(static function (OrderDocumentStruct $document) use ($type) {
            return $document->getDocumentType() === $type;
        });
    }
}
