<?php

declare(strict_types=1);

namespace ReiffIntegrations\Sap\Offer\ApiClient;

use ReiffIntegrations\Sap\Struct\OfferDocumentCollection;

class OfferReadApiResponse
{
    private bool $success;
    private string $rawResponse;
    private OfferDocumentCollection $documents;
    private ?string $returnMessage;

    public function __construct(
        bool $success,
        string $rawResponse,
        OfferDocumentCollection $documents,
        ?string $returnMessage = null
    ) {
        $this->success       = $success;
        $this->rawResponse   = $rawResponse;
        $this->documents     = $documents;
        $this->returnMessage = $returnMessage;
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getRawResponse(): string
    {
        return $this->rawResponse;
    }

    public function getReturnMessage(): ?string
    {
        return $this->returnMessage;
    }

    public function getDocuments(): OfferDocumentCollection
    {
        return $this->documents;
    }
}
