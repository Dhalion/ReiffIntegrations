<?php

declare(strict_types=1);

namespace ReiffIntegrations\MeDaPro\Cleaner;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Uuid\Uuid;

class ProductActivator
{
    const LIMIT = 500;

    public function __construct(
        private readonly Connection $connection,
        private readonly EntityRepository $productRepository
    ) {
    }

    // activates variants that have at least 1 assortment
    public function activateVariants(Context $context): void
    {
        while($payload = $this->fetchVariantsToActivate()) {
            $this->productRepository->update($payload, $context);
        }
    }

    // deletes variants that have no assortments
    public function deleteVariants(Context $context): void
    {
        while($payload = $this->fetchVariantsToDelete()) {
            $this->productRepository->delete($payload, $context);
        }
    }

    private function fetchVariantsToDelete(): ?array
    {
        $sql = <<<SQL
        SELECT `product`.`id`
        FROM `product`
        LEFT JOIN `swag_dynamic_access_product_rule` AS `rule` ON `product`.`id` = `rule`.`product_id`
        WHERE `rule`.`product_id` IS NULL
        AND `product`.`parent_id` IS NOT NULL
        AND `product`.`child_count` IS NULL
        ORDER BY `product`.`id` ASC
        LIMIT :actualLimit
        SQL;

        $result = $this->connection->fetchAllAssociative($sql, [
            'actualLimit' => self::LIMIT
        ], [
            'actualLimit' => ParameterType::INTEGER
        ]);

        if(!$result) {
            return null;
        }

        $payloadArray = [];
        foreach($result as $row) {
            $payloadArray[] = [
                'id' => Uuid::fromBytesToHex($row['id'])
            ];
        }

        return $payloadArray;

    }

    // activates main products, that at least 1 variant is active
    public function activateMainProducts(Context $context): void
    {
        while($payload = $this->fetchMainProductsToActivate()) {
            $this->productRepository->update($payload, $context);
        }

    }

    // deactivates main products, when every variant is not active
    public function deactivateMainProducts(Context $context): void
    {
        while($payload = $this->fetchMainProductsToDeactivate()) {
            $this->productRepository->update($payload, $context);
        }
    }

    private function fetchVariantsToActivate(): ?array
    {
        $sql = <<<SQL
        SELECT `product`.`id`
        FROM `product`
        LEFT JOIN `swag_dynamic_access_product_rule` AS `rule` ON `product`.`id` = `rule`.`product_id`
        WHERE `rule`.`product_id` IS NOT NULL
        AND `product`.`parent_id` IS NOT NULL
        AND `product`.`child_count` IS NULL
        AND `product`.`active` = 0
        ORDER BY `product`.`id` ASC
        LIMIT :actualLimit
        SQL;

        $result = $this->connection->fetchAllAssociative($sql, [
            'actualLimit' => self::LIMIT
        ], [
            'actualLimit' => ParameterType::INTEGER
        ]);

        if(!$result) {
            return null;
        }

        $payloadArray = [];
        foreach($result as $row) {
            $payloadArray[] = [
                'id' => Uuid::fromBytesToHex($row['id']),
                'active' => true
            ];
        }

        return $payloadArray;
    }

    private function fetchMainProductsToActivate(): ?array
    {
        $sql = <<<SQL
        SELECT p1.id
        FROM product p1
        WHERE p1.id IN (
            SELECT p2.parent_id
            FROM product p2
            WHERE p2.active = 1
        )
        AND p1.parent_id IS NULL
        AND p1.child_count > 0
        AND p1.active = 0
        ORDER BY `p1`.`id` ASC
        LIMIT :actualLimit
        OFFSET :actualOffset
        SQL;

        $result = $this->connection->fetchAllAssociative($sql, [
            'actualLimit' => self::LIMIT
        ], [
            'actualLimit' => ParameterType::INTEGER
        ]);

        if(!$result) {
            return null;
        }

        $payloadArray = [];
        foreach($result as $row) {
            $payloadArray[] = [
                'id' => Uuid::fromBytesToHex($row['id']),
                'active' => true
            ];
        }

        return $payloadArray;
    }

    private function fetchMainProductsToDeactivate(): ?array
    {
        $sql = <<<SQL
    SELECT p1.id
    FROM product p1
    WHERE p1.active = 1
    AND p1.parent_id IS NULL
    AND NOT EXISTS (
        SELECT 1
        FROM product p2
        WHERE p2.parent_id = p1.id
        AND p2.active = 1
    )
    ORDER BY `p1`.`id` ASC
    LIMIT :actualLimit
SQL;

        $result = $this->connection->fetchAllAssociative($sql, [
            'actualLimit' => self::LIMIT
        ], [
            'actualLimit' => ParameterType::INTEGER
        ]);

        if(!$result) {
            return null;
        }

        $payloadArray = [];
        foreach($result as $row) {
            $payloadArray[] = [
                'id' => Uuid::fromBytesToHex($row['id']),
                'active' => false
            ];
        }

        return $payloadArray;
    }
}
