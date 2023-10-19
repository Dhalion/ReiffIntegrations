<?php

declare(strict_types=1);

namespace ReiffIntegrations\Sap\Page\Orders;

use ReiffIntegrations\Sap\Struct\OrderCollection;
use Shopware\Storefront\Page\Page;

class OrdersPage extends Page
{
    private const DEFAULT_PERIOD = '3 months ago';

    private ?OrderCollection $orders      = null;
    private bool $success                 = false;
    private ?\DateTimeImmutable $fromDate = null;
    private ?\DateTimeImmutable $toDate   = null;

    public function getOrders(): ?OrderCollection
    {
        return $this->orders;
    }

    public function setOrders(OrderCollection $orders): void
    {
        $this->orders = $orders;
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function setSuccess(bool $success): void
    {
        $this->success = $success;
    }

    public function getFromDate(): \DateTimeImmutable
    {
        if ($this->fromDate && $this->toDate && $this->fromDate > $this->toDate) {
            return $this->toDate;
        }

        return $this->fromDate ?? (new \DateTimeImmutable())->modify(self::DEFAULT_PERIOD);
    }

    public function setFromDate(\DateTimeImmutable $fromDate): void
    {
        $this->fromDate = $fromDate;
    }

    public function getToDate(): \DateTimeImmutable
    {
        if ($this->fromDate && $this->toDate && $this->fromDate > $this->toDate) {
            return $this->fromDate;
        }

        return $this->toDate ?? new \DateTimeImmutable();
    }

    public function setToDate(\DateTimeImmutable $toDate): void
    {
        $this->toDate = $toDate;
    }
}
