<?php

declare(strict_types=1);

namespace ReiffIntegrations\MeDaPro\Cleaner;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Uuid\Uuid;

class CategoryActivator
{
    public const LIMIT = 500;

    public function __construct(
        private readonly Connection $connection,
        private readonly EntityRepository $categoryRepository
    ) {
    }

    // Activates categories, where at least 1 product in category is active
    public function activateCategories(Context $context): void
    {
        while ($payload = $this->fetchCategoriesToActivate()) {
            $this->categoryRepository->update($payload, $context);
        }
    }

    // Deactivates categories, where all products in category are inactive.
    public function deactivateCategories(Context $context): void
    {
        while ($payload = $this->fetchCategoriesToDeactivate()) {
            $this->categoryRepository->update($payload, $context);
        }
    }

    private function fetchCategoriesToActivate(): ?array
    {
        $sql = <<<SQL
            SELECT c.id
            FROM category c
            WHERE c.active = 0
            AND EXISTS (
                SELECT 1
                FROM product_category pc
                JOIN product p ON pc.product_id = p.id
                WHERE pc.category_id = c.id
                AND p.active = 1
            )
            ORDER BY `c`.`id` ASC
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
                'id'     => Uuid::fromBytesToHex($row['id']),
                'active' => true,
            ];
        }

        return $payloadArray;
    }

    private function fetchCategoriesToDeactivate(): ?array
    {
        $sql = '
            SELECT c.id
            FROM category c
            WHERE c.active = 1
            AND NOT EXISTS (
                SELECT 1
                FROM product_category pc
                JOIN product p ON pc.product_id = p.id
                WHERE pc.category_id = c.id
                AND p.active = 1
            )
            AND EXISTS (
                SELECT 1
                FROM product_category pc
                JOIN product p ON pc.product_id = p.id
                WHERE pc.category_id = c.id
            )
            ORDER BY `c`.`id` ASC
            LIMIT :actualLimit
        ';

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
                'id'     => Uuid::fromBytesToHex($row['id']),
                'active' => false,
            ];
        }

        return $payloadArray;
    }
}
