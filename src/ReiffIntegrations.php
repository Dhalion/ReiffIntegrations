<?php

declare(strict_types=1);

namespace ReiffIntegrations;

use Doctrine\DBAL\Connection;
use ReiffIntegrations\Installer\CustomFieldInstaller;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;

class ReiffIntegrations extends Plugin
{
    public function install(InstallContext $installContext): void
    {
        parent::install($installContext);

        (new CustomFieldInstaller($this->container))->install($installContext);
    }

    public function update(UpdateContext $updateContext): void
    {
        parent::update($updateContext);

        (new CustomFieldInstaller($this->container))->install($updateContext);
    }

    public function activate(ActivateContext $activateContext): void
    {
        parent::activate($activateContext);
    }

    public function deactivate(DeactivateContext $deactivateContext): void
    {
        parent::deactivate($deactivateContext);
    }

    public function uninstall(UninstallContext $uninstallContext): void
    {
        parent::uninstall($uninstallContext);

        if (!$uninstallContext->keepUserData()) {
            /** @var Connection $connection */
            $connection = $this->container->get(Connection::class);
            $connection->executeStatement('DROP TABLE `reiff_category`');
            $connection->executeStatement('DROP TABLE `reiff_customer`');
            $connection->executeStatement('DROP TABLE `reiff_order`');
            $connection->executeStatement('ALTER TABLE `category` DROP COLUMN `reiffCategory`');
            $connection->executeStatement('ALTER TABLE `customer` DROP COLUMN `reiffCustomer`');
            $connection->executeStatement('ALTER TABLE `order` DROP COLUMN `reiffOrder`');
        }
    }

    public function getTemplatePriority(): int
    {
        return 1;
    }
}
