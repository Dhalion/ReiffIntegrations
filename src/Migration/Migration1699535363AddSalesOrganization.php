<?php

declare(strict_types=1);

namespace ReiffIntegrations\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1699535363AddSalesOrganization extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1699535363;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            ALTER TABLE `reiff_customer`
            ADD COLUMN `sales_organization` VARCHAR(255) NULL;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
