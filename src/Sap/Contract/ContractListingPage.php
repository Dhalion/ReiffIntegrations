<?php

declare(strict_types=1);

namespace ReiffIntegrations\Sap\Contract;

use ReiffIntegrations\Sap\Contract\Struct\ContractListCollection;
use Shopware\Storefront\Page\Page;

class ContractListingPage extends Page
{
    protected ?ContractListCollection $contracts = null;
    protected bool $success                      = false;
    private ?\DateTimeImmutable $fromDate        = null;
    private ?\DateTimeImmutable $toDate          = null;

    public function getContracts(): ?ContractListCollection
    {
        return $this->contracts;
    }

    public function setContracts(?ContractListCollection $contracts): void
    {
        $this->contracts = $contracts;
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function setSuccess(bool $success): void
    {
        $this->success = $success;
    }

    public function getFromDate(): ?\DateTimeImmutable
    {
        if ($this->fromDate && $this->toDate && $this->fromDate > $this->toDate) {
            return $this->toDate;
        }

        return $this->fromDate;
    }

    public function setFromDate(\DateTimeImmutable $fromDate): void
    {
        $this->fromDate = $fromDate;
    }

    public function getToDate(): ?\DateTimeImmutable
    {
        if ($this->fromDate && $this->toDate && $this->fromDate > $this->toDate) {
            return $this->fromDate;
        }

        return $this->toDate;
    }

    public function setToDate(\DateTimeImmutable $toDate): void
    {
        $this->toDate = $toDate;
    }
}
