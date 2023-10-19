<?php

declare(strict_types=1);

namespace ReiffIntegrations\Sap\Offer\ApiClient\Pdf;

use ReiffIntegrations\Sap\Struct\PdfStruct;

class OfferPdfApiResponse
{
    public function __construct(
        private readonly bool $success,
        private readonly string $rawResponse,
        private readonly PdfStruct $document,
    ) {
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getRawResponse(): string
    {
        return $this->rawResponse;
    }

    public function getDocument(): PdfStruct
    {
        return $this->document;
    }
}
