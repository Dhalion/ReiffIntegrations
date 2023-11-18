<?php

declare(strict_types=1);

namespace ReiffIntegrations\MeDaPro\Cleaner;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Shopware\Core\Framework\Uuid\Uuid;

class SortmentRemoval
{
    public const LIMIT = 500;

    public function __construct(
        private readonly Connection $connection
    ) {
    }

    public function removeNotIncludedProductSortiments(string $catalogId, ?string $ruleId, array $sortimentProductNumbers): void
    {
        if (!$ruleId) {
            return;
        }

        $batchArray                 = [];
        $productIdsToRemoveSortment = [];
        $offset                     = 0;

        while ($products = $this->fetchProductsFromSortiment($catalogId, $ruleId, $offset)) {
            foreach ($products as $product) {
                if (!in_array($product['product_number'], $sortimentProductNumbers)) {
                    $productIdsToRemoveSortment[] = $product['id'];
                }
            }

            if (count($productIdsToRemoveSortment) >= 500) {
                $batchArray[]               = $productIdsToRemoveSortment;
                $productIdsToRemoveSortment = [];
            }

            $offset += self::LIMIT;
        }

        if ($productIdsToRemoveSortment) {
            $batchArray[] = $productIdsToRemoveSortment;
        }

        foreach ($batchArray as $batch) {
            $this->removeSortment($batch, $ruleId);
        }

        $this->removeSortmentFromChildren($ruleId);
    }

    public function fetchProductsFromSortiment(string $catalogId, string $ruleId, int $offset): ?array
    {
        $sql = <<<SQL
            SELECT product.id, product.product_number
            FROM product
            LEFT JOIN product parent_product ON product.parent_id = parent_product.id
            LEFT JOIN product_category ON parent_product.id = product_category.product_id
            LEFT JOIN reiff_category ON product_category.category_id = reiff_category.category_id
            LEFT JOIN swag_dynamic_access_product_rule ON product.id = swag_dynamic_access_product_rule.product_id
            WHERE reiff_category.catalog_id = :catalogId
            AND swag_dynamic_access_product_rule.rule_id = :ruleId
            LIMIT :actualLimit
            OFFSET :actualOffset;
            SQL;

        $result = $this->connection->fetchAllAssociative($sql, [
            'catalogId'    => $catalogId,
            'ruleId'       => Uuid::fromHexToBytes($ruleId),
            'actualLimit'  => self::LIMIT,
            'actualOffset' => $offset,
        ], [
            'actualLimit'  => ParameterType::INTEGER,
            'actualOffset' => ParameterType::INTEGER,
        ]);

        return $result ?: null;
    }

    private function removeSortment(array $productIds, string $ruleId): void
    {
        $sql = <<<SQL
            DELETE FROM swag_dynamic_access_product_rule
            WHERE rule_id = :ruleId
            AND product_id IN (:productIds)
            SQL;

        $this->connection->executeStatement($sql, [
           'ruleId'     => Uuid::fromHexToBytes($ruleId),
           'productIds' => $productIds,
        ], [
            'productIds' => Connection::PARAM_STR_ARRAY,
        ]);
    }

    private function removeSortmentFromChildren(string $ruleId): void
    {
        $sql = <<<SQL
            DELETE swag_dynamic_access_product_rule
            FROM swag_dynamic_access_product_rule
            JOIN product ON swag_dynamic_access_product_rule.product_id = product.id
            LEFT JOIN swag_dynamic_access_product_rule AS parent_rule ON product.parent_id = parent_rule.product_id
                AND parent_rule.rule_id = :ruleId
            WHERE swag_dynamic_access_product_rule.rule_id = :ruleId
            AND parent_rule.product_id IS NULL
            AND product.parent_id IS NOT NULL;
            SQL;

        $this->connection->executeStatement($sql, [
            'ruleId' => Uuid::fromHexToBytes($ruleId),
        ]);
    }
}
