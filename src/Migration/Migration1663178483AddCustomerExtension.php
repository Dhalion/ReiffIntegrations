<?php

declare(strict_types=1);

namespace ReiffIntegrations\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\InheritanceUpdaterTrait;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1663178483AddCustomerExtension extends MigrationStep
{
    use InheritanceUpdaterTrait;

    public function getCreationTimestamp(): int
    {
        return 1663178483;
    }

    public function update(Connection $connection): void
    {
        // implement update
        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `reiff_customer` (
                `customer_id` BINARY(16) NOT NULL,
                `debtor_number` VARCHAR(32) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`customer_id`),
                KEY `fk.reiff_customer.customer_id` (`customer_id`),
                CONSTRAINT `fk.reiff_customer.customer_id` FOREIGN KEY (`customer_id`) REFERENCES `customer` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
        );

        $this->updateInheritance($connection, 'customer', 'reiffCustomer');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
