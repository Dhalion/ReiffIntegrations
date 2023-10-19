<?php

declare(strict_types=1);

namespace ReiffIntegrations\Sap\Offer\Page;

use ReiffIntegrations\Sap\Struct\OfferDocumentStruct;
use Shopware\Storefront\Page\Page;

class OfferDetailPage extends Page
{
    protected ?OfferDocumentStruct $offer = null;
    protected array $errors               = [];

    public function getOffer(): ?OfferDocumentStruct
    {
        return $this->offer;
    }

    public function setOffer(?OfferDocumentStruct $offer): void
    {
        $this->offer = $offer;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function hasErrors(): bool
    {
        return $this->offer === null || count($this->errors) > 0;
    }

    public function setErrors(array $errors): void
    {
        $this->errors = $errors;
    }

    public function addError(\Throwable $error): void
    {
        $this->errors[] = $error;
    }
}
