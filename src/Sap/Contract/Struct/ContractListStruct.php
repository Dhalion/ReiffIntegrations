<?php

declare(strict_types=1);

namespace ReiffIntegrations\Sap\Contract\Struct;

use Shopware\Core\Framework\Struct\Struct;

class ContractListStruct extends Struct
{
    public function __construct(
        private readonly ?string $contractNumber,
        private readonly ?string $contractType,
        private readonly ?string $salesGroup,
        private readonly ?string $salesGroupDescription,
        private readonly ?\DateTimeImmutable $documentDate,
        private readonly ?string $documentReference,
        private readonly ?\DateTimeImmutable $documentOrderDate,
        private readonly ?\DateTimeImmutable $validFrom,
        private readonly ?\DateTimeImmutable $validTo,
        private readonly ?string $customer,
        private readonly ?string $customerName,
        private readonly ?\DateTimeImmutable $sapCreateDateTime,
        private readonly ?string $status,
        private readonly ?string $statusDescription,
        private readonly ?float $documentNetValue,
        private readonly ?string $documentCurrency
    ) {
    }

    public function getContractNumber(): ?string
    {
        return $this->contractNumber;
    }

    public function getContractType(): ?string
    {
        return $this->contractType;
    }

    public function getSalesGroup(): ?string
    {
        return $this->salesGroup;
    }

    public function getSalesGroupDescription(): ?string
    {
        return $this->salesGroupDescription;
    }

    public function getDocumentDate(): ?\DateTimeImmutable
    {
        return $this->documentDate;
    }

    public function getDocumentReference(): ?string
    {
        return $this->documentReference;
    }

    public function getDocumentOrderDate(): ?\DateTimeImmutable
    {
        return $this->documentOrderDate;
    }

    public function getValidFrom(): ?\DateTimeImmutable
    {
        return $this->validFrom;
    }

    public function getValidTo(): ?\DateTimeImmutable
    {
        return $this->validTo;
    }

    public function getCustomer(): ?string
    {
        return $this->customer;
    }

    public function getCustomerName(): ?string
    {
        return $this->customerName;
    }

    public function getSapCreateDateTime(): ?\DateTimeImmutable
    {
        return $this->sapCreateDateTime;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function getStatusDescription(): ?string
    {
        return $this->statusDescription;
    }

    public function getDocumentNetValue(): ?float
    {
        return $this->documentNetValue;
    }

    public function getDocumentCurrency(): ?string
    {
        return $this->documentCurrency;
    }
}
