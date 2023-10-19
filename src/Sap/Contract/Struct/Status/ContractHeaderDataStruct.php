<?php

declare(strict_types=1);

namespace ReiffIntegrations\Sap\Contract\Struct\Status;

use Shopware\Core\Framework\Struct\Struct;

class ContractHeaderDataStruct extends Struct
{
    public function __construct(
        private readonly ?string $salesOrg,
        private readonly ?string $distributionChannel,
        private readonly ?string $division,
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
        private readonly ?float $documentFreightCost,
        private readonly ?float $documentExtraHeaderCost,
        private readonly ?string $documentCurrency,
        private readonly ?string $documentCurrencyIso
    ) {
    }

    public function getSalesOrg(): ?string
    {
        return $this->salesOrg;
    }

    public function getDistributionChannel(): ?string
    {
        return $this->distributionChannel;
    }

    public function getDivision(): ?string
    {
        return $this->division;
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

    public function getDocumentFreightCost(): ?float
    {
        return $this->documentFreightCost;
    }

    public function getDocumentExtraHeaderCost(): ?float
    {
        return $this->documentExtraHeaderCost;
    }

    public function getDocumentCurrency(): ?string
    {
        return $this->documentCurrency;
    }

    public function getDocumentCurrencyIso(): ?string
    {
        return $this->documentCurrencyIso;
    }
}
