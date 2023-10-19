<?php

declare(strict_types=1);

namespace ReiffIntegrations\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\InheritanceUpdaterTrait;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1666001881AddMediaExtension extends MigrationStep
{
    use InheritanceUpdaterTrait;

    public function getCreationTimestamp(): int
    {
        return 1666001881;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `reiff_media` (
                `media_id` BINARY(16) NOT NULL,
                `hash` VARCHAR(32) NULL,
                `original_path` VARCHAR(255) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`media_id`),
                KEY `fk.reiff_media.media_id` (`media_id`),
                CONSTRAINT `fk.reiff_media.media_id` FOREIGN KEY (`media_id`) REFERENCES `media` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
        );

        $this->updateInheritance($connection, 'media', 'reiffMedia');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
