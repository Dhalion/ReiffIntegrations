<?php

declare(strict_types=1);

namespace ReiffIntegrations\Util;

use Shopware\Core\Framework\Api\Sync\SyncBehavior;
use Shopware\Core\Framework\Api\Sync\SyncOperation;
use Shopware\Core\Framework\Api\Sync\SyncServiceInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Contracts\Service\ResetInterface;

class EntitySyncer implements ResetInterface
{
    private SyncServiceInterface $syncService;

    /** @var SyncOperation[] */
    private array $operations = [];

    public function __construct(SyncServiceInterface $syncService)
    {
        $this->syncService = $syncService;
    }

    /**
     * @param string $entity  Entity name constant (e.g. ProductDefinition::ENTITY_NAME)
     * @param string $method  SyncOperation constant (e.g. SyncOperation::ACTION_UPSERT)
     * @param array  $payload Operation payload (e.g. fields to update, array of arrays)
     */
    public function addOperation(string $entity, string $method, array $payload): void
    {
        $this->operations[] = new SyncOperation(
            Uuid::randomHex(),
            $entity,
            $method,
            [$payload]
        );
    }

    /**
     * @param string $entity   Entity name constant (e.g. ProductDefinition::ENTITY_NAME)
     * @param string $method   SyncOperation constant (e.g. SyncOperation::ACTION_UPSERT)
     * @param array  $payloads Operation payload (e.g. fields to update, array of arrays)
     */
    public function addOperations(string $entity, string $method, array $payloads): void
    {
        $this->operations[] = new SyncOperation(
            Uuid::randomHex(),
            $entity,
            $method,
            array_values($payloads)
        );
    }

    public function flush(Context $context): void
    {
        try {
            if (empty($this->operations)) {
                return;
            }

            $behavior = new SyncBehavior();
            $this->syncService->sync($this->operations, $context, $behavior);
        } finally {
            $this->operations = [];
        }
    }

    public function getOperations(): array
    {
        return $this->operations;
    }

    public function reset(): void
    {
        $this->operations = [];
    }
}
