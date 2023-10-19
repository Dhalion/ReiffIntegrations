<?php

declare(strict_types=1);

namespace ReiffIntegrations\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\InheritanceUpdaterTrait;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1665571017AddCategoryExtension extends MigrationStep
{
    use InheritanceUpdaterTrait;

    public function getCreationTimestamp(): int
    {
        return 1665571017;
    }

    public function update(Connection $connection): void
    {
        // implement update
        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `reiff_category` (
                `category_id` BINARY(16) NOT NULL,
                `catalog_id` VARCHAR(32) NULL,
                `uid` VARCHAR(32) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`category_id`),
                KEY `fk.reiff_category.category_id` (`category_id`),
                CONSTRAINT `fk.reiff_category.category_id` FOREIGN KEY (`category_id`) REFERENCES `category` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
        );

        $this->updateInheritance($connection, 'category', 'reiffCategory');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
