<?php

declare(strict_types=1);

namespace ReiffIntegrations\Sap\Struct;

use Shopware\Core\Framework\Struct\Struct;

class PdfStruct extends Struct
{
    public function __construct(
        private readonly ?string $documentNumber = null,
        private readonly ?string $customerNumber = null,
        private readonly ?string $fileName = null,
        private readonly ?string $pdf = null,
    ) {
    }

    public function getDocumentNumber(): ?string
    {
        return $this->documentNumber;
    }

    public function getCustomerNumber(): ?string
    {
        return $this->customerNumber;
    }

    public function getFileName(): ?string
    {
        return $this->fileName;
    }

    public function getPdf(): ?string
    {
        return $this->pdf;
    }
}
