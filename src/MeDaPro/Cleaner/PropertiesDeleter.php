<?php

declare(strict_types=1);

namespace ReiffIntegrations\MeDaPro\Cleaner;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Uuid\Uuid;

class PropertiesDeleter
{
    private const LIMIT = 500;

    public function __construct(
        private readonly Connection $connection,
        private readonly EntityRepository $propertyGroupOptionRepository,
        private readonly EntityRepository $propertyGroupRepository
    ) {
    }

    // Deletes unused properties
    public function deleteProperties(Context $context): void
    {
        while ($payload = $this->fetchPropertiesToDelete()) {
            $this->propertyGroupOptionRepository->delete($payload, $context);
        }
    }

    public function deleteGroups(Context $context): void
    {
        while ($payload = $this->fetchGroupsToDelete()) {
            $this->propertyGroupRepository->delete($payload, $context);
        }
    }

    private function fetchPropertiesToDelete(): ?array
    {
        $sql = <<<SQL
            SELECT id
            FROM property_group_option
            WHERE id NOT IN (
                SELECT DISTINCT property_group_option_id
                FROM product_property
                UNION
                SELECT DISTINCT property_group_option_id
                FROM product_option
                UNION
                SELECT DISTINCT property_group_option_id
                FROM product_configurator_setting
            )
            ORDER BY id ASC
            LIMIT :actualLimit
            SQL;
        $result = $this->connection->fetchAllAssociative($sql, [
            'actualLimit' => self::LIMIT,
        ], [
            'actualLimit' => ParameterType::INTEGER,
        ]);

        if (!$result) {
            return null;
        }

        $payloadArray = [];
        foreach ($result as $row) {
            $payloadArray[] = [
                'id' => Uuid::fromBytesToHex($row['id']),
            ];
        }

        return $payloadArray;
    }

    private function fetchGroupsToDelete(): ?array
    {
        $sql = <<<SQL
            SELECT id
            FROM property_group
            WHERE id NOT IN (
                SELECT DISTINCT property_group_id
                FROM property_group_option
            )
            ORDER BY id ASC
            LIMIT :actualLimit
            SQL;

        $result = $this->connection->fetchAllAssociative($sql, [
            'actualLimit' => self::LIMIT,
        ], [
            'actualLimit' => ParameterType::INTEGER,
        ]);

        if (!$result) {
            return null;
        }

        $payloadArray = [];
        foreach ($result as $row) {
            $payloadArray[] = [
                'id' => Uuid::fromBytesToHex($row['id']),
            ];
        }

        return $payloadArray;
    }
}
