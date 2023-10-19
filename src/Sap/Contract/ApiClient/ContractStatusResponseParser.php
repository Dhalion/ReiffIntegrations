<?php

declare(strict_types=1);

namespace ReiffIntegrations\Sap\Contract\ApiClient;

use Psr\Log\LoggerInterface;
use ReiffIntegrations\Sap\Contract\Struct\ContractStatusStruct;
use ReiffIntegrations\Sap\Contract\Struct\Status\ContractAddressCollection;
use ReiffIntegrations\Sap\Contract\Struct\Status\ContractAddressStruct;
use ReiffIntegrations\Sap\Contract\Struct\Status\ContractHeaderDataStruct;
use ReiffIntegrations\Sap\Contract\Struct\Status\ContractItemDataCollection;
use ReiffIntegrations\Sap\Contract\Struct\Status\ContractItemDataStruct;
use ReiffIntegrations\Sap\Contract\Struct\Status\ContractItemUsageCollection;
use ReiffIntegrations\Sap\Contract\Struct\Status\ContractItemUsageStruct;
use ReiffIntegrations\Sap\Util\AbstractResponseParser;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

class ContractStatusResponseParser extends AbstractResponseParser
{
    public const INVALID_DATE = '0000-00-00';

    private const RETURN_START_STR  = '<ES_RETURN>';
    private const RETURN_END_STR    = '</ES_RETURN>';
    private const RETURN_SHORT_HAND = '<ES_RETURN/>';

    private const LIST_START_STR  = '<ES_CUSTOMER_CONTRACT_DETAILS>';
    private const LIST_END_STR    = '</ES_CUSTOMER_CONTRACT_DETAILS>';
    private const LIST_SHORT_HAND = '<ES_CUSTOMER_CONTRACT_DETAILS/>';

    protected array $countriesByIsoCode = [];

    public function __construct(private readonly LoggerInterface $logger, private readonly EntityRepository $countryRepository)
    {
    }

    public function parseResponse(bool $success, string $rawResponse, Context $context): ContractStatusResponse
    {
        $contractStatus = new ContractStatusStruct();

        if (empty($rawResponse)) {
            return new ContractStatusResponse($success, $rawResponse, $contractStatus);
        }

        try {
            $xmlString = (string) preg_replace("/(<\/?)(\w+):([^>]*>)/", '$1$2$3', $rawResponse);

            $xmlString = str_replace(
                self::RETURN_SHORT_HAND,
                self::RETURN_START_STR . self::RETURN_END_STR,
                $xmlString
            );

            $xmlString = str_replace(
                self::LIST_SHORT_HAND,
                self::LIST_START_STR . self::LIST_END_STR,
                $xmlString
            );

            $returnPart = $this->getStringPart($xmlString, self::RETURN_START_STR, self::RETURN_END_STR);
            $xmlReturn  = new \SimpleXMLElement(self::RETURN_START_STR . $returnPart . self::RETURN_END_STR);
            $returnData = (array) json_decode((string) json_encode($xmlReturn), true);

            $listPart = $this->getStringPart($xmlString, self::LIST_START_STR, self::LIST_END_STR);
            $xmlList  = new \SimpleXMLElement(self::LIST_START_STR . $listPart . self::LIST_END_STR);
            $listData = (array) json_decode((string) json_encode($xmlList), true);
        } catch (\Exception $exception) {
            $this->logger->error('The XML file cannot be generated', [
                'response' => $rawResponse,
                'error'    => $exception->getMessage(),
            ]);

            return new ContractStatusResponse($success, $rawResponse, $contractStatus);
        }

        if (count($returnData) === 0) {
            $this->logger->error('Return data array is empty', [
                'xmlReturn' => $xmlReturn,
            ]);

            return new ContractStatusResponse($success, $rawResponse, $contractStatus);
        }

        try {
            $contractStatus = $this->buildContractStatus($listData, $context);
        } catch (\Exception $exception) {
            $this->logger->error('Error determining the contract status', [
                'response' => $rawResponse,
                'error'    => $exception->getMessage(),
                'listData' => $listData,
            ]);
        }

        /** @var null|string $returnMessage */
        $returnMessage = $returnData['MESSAGE'] ?? null;

        return new ContractStatusResponse(
            $success,
            $rawResponse,
            $contractStatus,
            $returnMessage ? (string) $returnMessage : null,
        );
    }

    private function buildContractStatus(array $listData, Context $context): ContractStatusStruct
    {
        $contractNumber = $this->getData($listData, 'CONTRACT_NUMBER', 'string', true);
        $contractType   = $this->getData($listData, 'CONTRACT_TYPE', 'string', true);

        $contractStatusHeaderData = null;
        $contractAddressData      = null;

        if (isset($listData['CONTRACT_HEADER_DATA']) && is_array($listData['CONTRACT_HEADER_DATA']) && !empty($listData['CONTRACT_HEADER_DATA'])) {
            if (isset($listData['CONTRACT_HEADER_DATA']['BUSINESS_DATA']) && is_array($listData['CONTRACT_HEADER_DATA']['BUSINESS_DATA']) && !empty($listData['CONTRACT_HEADER_DATA']['BUSINESS_DATA'])) {
                $contractStatusHeaderData = $this->buildContractStatusHeaderData($listData['CONTRACT_HEADER_DATA']['BUSINESS_DATA']);
            }

            if (isset($listData['CONTRACT_HEADER_DATA']['PARTNER_ADDRESS']) && is_array($listData['CONTRACT_HEADER_DATA']['PARTNER_ADDRESS']) && !empty($listData['CONTRACT_HEADER_DATA']['PARTNER_ADDRESS'])) {
                $contractAddressData = $this->buildContractStatusAddressData($listData['CONTRACT_HEADER_DATA']['PARTNER_ADDRESS'], $context);
            }
        }

        $contractStatusItemData = null;

        if (isset($listData['CONTRACT_ITEM_DATA']) && is_array($listData['CONTRACT_ITEM_DATA']) && !empty($listData['CONTRACT_ITEM_DATA'])) {
            $contractStatusItemData = $this->buildContractStatusItemData($listData['CONTRACT_ITEM_DATA']);
        }

        return new ContractStatusStruct(
            $contractNumber,
            $contractType,
            $contractStatusHeaderData,
            $contractAddressData,
            $contractStatusItemData
        );
    }

    private function buildContractStatusHeaderData(array $businessData): ContractHeaderDataStruct
    {
        return new ContractHeaderDataStruct(
            $this->getData($businessData, 'SALES_ORG', 'string', true),
            $this->getData($businessData, 'DISTRIBUTION_CHANNEL', 'string', true),
            $this->getData($businessData, 'DIVISION', 'string', true),
            $this->getData($businessData, 'SALES_GROUP', 'string', true),
            $this->getData($businessData, 'SALES_GROUP_DESCRIPTION', 'string', true),
            $this->getData($businessData, 'DOCUMENT_DATE', 'DateTime'),
            $this->getData($businessData, 'DOCUMENT_REFERENCE', 'string', true),
            $this->getData($businessData, 'DOCUMENT_ORDER_DATE', 'DateTime'),
            $this->getData($businessData, 'VALID_FROM', 'DateTime'),
            $this->getData($businessData, 'VALID_TO', 'DateTime'),
            $this->getData($businessData, 'CUSTOMER', 'string', true),
            $this->getData($businessData, 'CUSTOMER_NAME', 'string'),
            $this->getSapCreationDateTime($businessData['SAP_CREATE_DATE'], $businessData['SAP_CREATE_TIME']),
            $this->getData($businessData, 'STATUS', 'string', true),
            $this->getData($businessData, 'STATUS_DESCRIPTION', 'string', true),
            $this->getData($businessData, 'DOCUMENT_NET_VALUE', 'float'),
            $this->getData($businessData, 'DOCUMENT_FREIGHT_COST', 'float'),
            $this->getData($businessData, 'DOCUMENT_EXTRA_HEADER_COST', 'float'),
            $this->getData($businessData, 'DOCUMENT_CURRENCY', 'string'),
            $this->getData($businessData, 'DOCUMENT_CURRENCY_ISO', 'string')
        );
    }

    private function buildContractStatusAddressData(array $addressData, Context $context): ContractAddressCollection
    {
        $addresses = new ContractAddressCollection();

        foreach ($addressData as $addressType => $address) {
            if (!is_array($address) || empty($address)) {
                continue;
            }

            $addressStruct = new ContractAddressStruct(
                $addressType,
                $this->getData($address, 'NAME', 'string'),
                !is_array($address['NAME_2']) ? $address['NAME_2'] : null,
                $this->getData($address, 'STREET', 'string'),
                !is_array($address['HOUSE_NO_LONG']) ? $address['HOUSE_NO_LONG'] : null,
                $this->getData($address, 'POSTL_CODE', 'string'),
                $this->getData($address, 'CITY', 'string'),
                $this->getCountryName($address['COUNTRYISO'], $context),
                $this->getData($address, 'TELEPHONE', 'string'),
                $this->getData($address, 'TELEBOX', 'string'),
                $this->getData($address, 'FAX_NUMBER', 'string')
            );

            $addresses->add($addressStruct);
        }

        return $addresses;
    }

    private function buildContractStatusItemData(array $itemData): ContractItemDataCollection
    {
        $contractItemData = new ContractItemDataCollection();

        if (!isset($itemData['item']) || !is_array($itemData['item']) || count($itemData['item']) === 0) {
            return $contractItemData;
        }

        $listDataIterator = new \RecursiveArrayIterator($itemData['item']);

        if (!$listDataIterator->hasChildren()) {
            $itemData['item'][] = $itemData['item'];
        }

        foreach ($itemData['item'] as $item) {
            if (!is_array($item) || empty($item)) {
                continue;
            }

            $itemUsage = null;

            if (isset($item['ITEM_USAGE']) && !empty($item['ITEM_USAGE'])) {
                $itemUsage = $this->buildContractStatusItemUsage($item['ITEM_USAGE']);
            }

            $itemStruct = new ContractItemDataStruct(
                $this->getData($item, 'ITEM_NUMBER', 'string', true),
                $this->getData($item, 'MATERIAL_NUMBER', 'string', true),
                !is_array($item['CUSTOMER_MATERIAL_NUMBER']) ? $this->getData($item, 'CUSTOMER_MATERIAL_NUMBER', 'string') : null,
                $this->getData($item, 'SHORT_TEXT', 'string'),
                $this->getData($item, 'TARGET_QUANTITY', 'float'),
                $this->getData($item, 'USED_QUANTITY', 'float'),
                $this->getData($item, 'OPEN_QUANTITY', 'float'),
                $this->getData($item, 'SALES_UNIT', 'string'),
                $this->getData($item, 'SALES_UNIT_ISO', 'string'),
                $this->getData($item, 'NET_PRICE', 'float'),
                $this->getData($item, 'NET_VALUE', 'float'),
                $this->getData($item, 'CURRENCY', 'string'),
                $this->getData($item, 'CURRENCY_ISO', 'string'),
                $this->getData($item, 'STATUS_DESCRIPTION', 'string'),
                $itemUsage
            );

            $contractItemData->add($itemStruct);
        }

        return $contractItemData;
    }

    private function buildContractStatusItemUsage(array $itemUsage): ContractItemUsageCollection
    {
        $contractItemUsage = new ContractItemUsageCollection();

        if (!isset($itemUsage['item']) || !is_array($itemUsage['item']) || count($itemUsage['item']) === 0) {
            return $contractItemUsage;
        }

        $listDataIterator = new \RecursiveArrayIterator($itemUsage['item']);

        if (!$listDataIterator->hasChildren()) {
            $itemUsage['item'][] = $itemUsage['item'];
        }

        foreach ($itemUsage['item'] as $item) {
            if (!is_array($item) || empty($item)) {
                continue;
            }

            $itemUsageStruct = new ContractItemUsageStruct(
                $this->getData($item, 'ORDER_NUMBER', 'string', true),
                $this->getData($item, 'ORDER_ITEM_NUMBER', 'string', true),
                $this->getData($item, 'ORDER_DATE', 'DateTime'),
                $this->getData($item, 'ORDER_CUST_REFERENCE', 'string'),
                $this->getData($item, 'ORDER_CUST_DATE', 'DateTime'),
                $this->getData($item, 'ORDER_ITEM_QUANTITY', 'float'),
                $this->getData($item, 'ORDER_ITEM_UOM', 'string')
            );

            $contractItemUsage->add($itemUsageStruct);
        }

        return $contractItemUsage;
    }

    private function getCountryName(string $isoCode, Context $context): string
    {
        if (!array_key_exists($isoCode, $this->countriesByIsoCode)) {
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('iso', $isoCode));

            /** @var null|\Shopware\Core\System\Country\CountryEntity $country */
            $country = $this->countryRepository->search($criteria, $context)->first();

            if ($country) {
                $this->countriesByIsoCode[$isoCode] = (string) $country->getName();
            }
        }

        return array_key_exists($isoCode, $this->countriesByIsoCode) ? $this->countriesByIsoCode[$isoCode] : $isoCode;
    }
}
