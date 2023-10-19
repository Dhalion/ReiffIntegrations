<?php

declare(strict_types=1);

namespace ReiffIntegrations\Seeburger\DataAbstractionLayer;

use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;

class ReiffOrderEntity extends Entity
{
    protected ?OrderEntity $order             = null;
    protected ?string $orderId                = null;
    protected ?\DateTimeInterface $exportedAt = null;
    protected ?\DateTimeInterface $queuedAt   = null;
    protected ?\DateTimeInterface $notifiedAt = null;
    protected int $exportTries                = 0;

    public function getOrder(): ?OrderEntity
    {
        return $this->order;
    }

    public function setOrder(?OrderEntity $order): void
    {
        $this->order = $order;
    }

    public function getOrderId(): ?string
    {
        return $this->orderId;
    }

    public function setOrderId(string $orderId): void
    {
        $this->orderId = $orderId;
    }

    public function getExportedAt(): ?\DateTimeInterface
    {
        return $this->exportedAt;
    }

    public function setExportedAt(\DateTimeInterface $exportedAt): void
    {
        $this->exportedAt = $exportedAt;
    }

    public function getQueuedAt(): ?\DateTimeInterface
    {
        return $this->queuedAt;
    }

    public function setQueuedAt(\DateTimeInterface $queuedAt): void
    {
        $this->queuedAt = $queuedAt;
    }

    public function getNotifiedAt(): ?\DateTimeInterface
    {
        return $this->notifiedAt;
    }

    public function setNotifiedAt(\DateTimeInterface $notifiedAt): void
    {
        $this->notifiedAt = $notifiedAt;
    }

    public function getExportTries(): int
    {
        return $this->exportTries;
    }

    public function setExportTries(int $exportTries): void
    {
        $this->exportTries = $exportTries;
    }
}
