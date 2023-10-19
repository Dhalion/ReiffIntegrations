<?php

declare(strict_types=1);

namespace ReiffIntegrations\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Framework\Migration\InheritanceUpdaterTrait;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1663006368AddOrderExtension extends MigrationStep
{
    use InheritanceUpdaterTrait;

    public function getCreationTimestamp(): int
    {
        return 1663006368;
    }

    public function update(Connection $connection): void
    {
        // implement update
        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `reiff_order` (
                `order_version_id` BINARY(16) NOT NULL,
                `order_id` BINARY(16) NOT NULL,
                `exported_at` DATETIME(3) NULL,
                `queued_at` DATETIME(3) NULL,
                `notified_at` DATETIME(3) NULL,
                `export_tries` INT(3) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`order_id`,`order_version_id`),
                KEY `fk.reiff_order.order_id` (`order_id`,`order_version_id`),
                CONSTRAINT `fk.reiff_order.order_id` FOREIGN KEY (`order_id`,`order_version_id`) REFERENCES `order` (`id`,`version_id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
        );

        $this->updateInheritance($connection, OrderDefinition::ENTITY_NAME, 'reiffOrder');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
