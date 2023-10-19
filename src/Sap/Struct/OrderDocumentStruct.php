<?php

declare(strict_types=1);

namespace ReiffIntegrations\Sap\Struct;

use Shopware\Core\Framework\Struct\Struct;

class OrderDocumentStruct extends Struct
{
    public function __construct(
        private readonly string $documentNumber,
        private readonly string $documentType,
        private readonly \DateTimeInterface $documentDate,
        private readonly array $urls = [],
    ) {
    }

    public function getDocumentNumber(): string
    {
        return $this->documentNumber;
    }

    public function getDocumentType(): string
    {
        return $this->documentType;
    }

    public function getDocumentDate(): \DateTimeInterface
    {
        return $this->documentDate;
    }

    public function getUrls(): array
    {
        return $this->urls;
    }
}
