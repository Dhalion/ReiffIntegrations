<?php

declare(strict_types=1);

namespace ReiffIntegrations\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\InheritanceUpdaterTrait;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

class Migration1667561132AdjustConfigurationKey extends MigrationStep
{
    use InheritanceUpdaterTrait;

    public function getCreationTimestamp(): int
    {
        return 1667561132;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            '
            UPDATE `system_config`
            SET `configuration_key` = "ReiffIntegrations.config.categoryWithChildrenCmsPage"
            WHERE `configuration_key` = "ReiffIntegrations.config.categoryDefaultCmsPage"
;'
        );

        $connection->executeStatement(
            '
            INSERT INTO `system_config` (`id`, `configuration_key`, `configuration_value`, `sales_channel_id`, `created_at`, `updated_at`)
            SELECT ":binaryId", "ReiffIntegrations.config.categoryWithoutChildrenCmsPage", `configuration_value`, NULL, now(), NULL
            FROM `system_config`
            WHERE `configuration_key` = "ReiffIntegrations.config.categoryWithChildrenCmsPage";',
            ['binaryId' => Uuid::randomBytes()]
        );
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
