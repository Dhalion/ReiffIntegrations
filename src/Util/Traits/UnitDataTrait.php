<?php

declare(strict_types=1);

namespace ReiffIntegrations\Util\Traits;

use Doctrine\DBAL\Connection;
use ReiffIntegrations\Installer\CustomFieldInstaller;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @property Connection $connection
 */
trait UnitDataTrait
{
    protected array $unitData = [];

    protected function getSalesUnit(?string $elementData, ?string $default = null): ?string
    {
        if ($elementData === null || !array_key_exists($elementData, $this->unitData) || empty($this->unitData[$elementData])) {
            return $default;
        }

        return $this->unitData[$elementData]['name'] ?? ($this->unitData[$elementData]['shortCode'] ?? $default);
    }

    protected function updateProductUnits(string $languageId): void
    {
        $result = $this->connection->fetchAllAssociative(
            'SELECT short_code, name, custom_fields
            FROM unit_translation ut
            WHERE language_id = :languageId',
            ['languageId' => Uuid::fromHexToBytes($languageId)]
        );

        foreach ($result as $resultItem) {
            if (empty($resultItem['custom_fields'] ?? null)) {
                continue;
            }

            /** @var string $customFieldsString */
            $customFieldsString = $resultItem['custom_fields'];
            /** @var array|false $decodedFields */
            $decodedFields = json_decode($customFieldsString, true);

            if (!$customFieldsString || empty($decodedFields) || empty($decodedFields[CustomFieldInstaller::UNIT_SAP_IDENTIFIER])) {
                continue;
            }

            $this->unitData[$decodedFields[CustomFieldInstaller::UNIT_SAP_IDENTIFIER]] = [
                'shortCode' => $resultItem['short_code'],
                'name'      => $resultItem['name'],
            ];
        }
    }
}
