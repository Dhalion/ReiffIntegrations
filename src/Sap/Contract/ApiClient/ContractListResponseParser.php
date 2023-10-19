<?php

declare(strict_types=1);

namespace ReiffIntegrations\Sap\Contract\ApiClient;

use Psr\Log\LoggerInterface;
use ReiffIntegrations\Sap\Contract\Struct\ContractListCollection;
use ReiffIntegrations\Sap\Contract\Struct\ContractListStruct;
use ReiffIntegrations\Sap\Util\AbstractResponseParser;

class ContractListResponseParser extends AbstractResponseParser
{
    public const INVALID_DATE = '0000-00-00';

    private const RETURN_START_STR  = '<ES_RETURN>';
    private const RETURN_END_STR    = '</ES_RETURN>';
    private const RETURN_SHORT_HAND = '<ES_RETURN/>';

    private const LIST_START_STR  = '<ET_CONTRACT_LIST>';
    private const LIST_END_STR    = '</ET_CONTRACT_LIST>';
    private const LIST_SHORT_HAND = '<ET_CONTRACT_LIST/>';

    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    public function parseResponse(bool $success, string $rawResponse): ContractListResponse
    {
        $contracts = new ContractListCollection();

        if (empty($rawResponse)) {
            return new ContractListResponse($success, $rawResponse, $contracts);
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

            return new ContractListResponse($success, $rawResponse, $contracts);
        }

        if (count($returnData) === 0) {
            $this->logger->error('Return data array is empty', [
                'xmlReturn' => $xmlReturn,
            ]);

            return new ContractListResponse($success, $rawResponse, $contracts);
        }

        try {
            $contracts = $this->buildContracts($listData);
        } catch (\Exception $exception) {
            $this->logger->error('Error determining the contracts', [
                'response' => $rawResponse,
                'error'    => $exception->getMessage(),
                'listData' => $listData,
            ]);
        }

        /** @var null|string $returnMessage */
        $returnMessage = $returnData['MESSAGE'] ?? null;

        return new ContractListResponse(
            $success,
            $rawResponse,
            $contracts,
            $returnMessage ? (string) $returnMessage : null,
        );
    }

    private function buildContracts(array $listData): ContractListCollection
    {
        $contracts = new ContractListCollection();

        if (!isset($listData['item']) || !is_array($listData['item']) || count($listData['item']) === 0) {
            return $contracts;
        }

        $listDataIterator = new \RecursiveArrayIterator($listData['item']);

        if (!$listDataIterator->hasChildren()) {
            $listData['item'][] = $listData['item'];
        }

        foreach ($listData['item'] as $contractData) {
            if (!is_array($contractData) || empty($contractData)) {
                continue;
            }

            $contract = new ContractListStruct(
                $this->getData($contractData, 'CONTRACT_NUMBER', 'string', true),
                $this->getData($contractData, 'CONTRACT_TYPE', 'string'),
                $this->getData($contractData, 'SALES_GROUP', 'string'),
                $this->getData($contractData, 'SALES_GROUP_DESCRIPTION', 'string'),
                $this->getData($contractData, 'DOCUMENT_DATE', 'DateTime'),
                $this->getData($contractData, 'DOCUMENT_REFERENCE', 'string'),
                $this->getData($contractData, 'DOCUMENT_ORDER_DATE', 'DateTime'),
                $this->getData($contractData, 'VALID_FROM', 'DateTime'),
                $this->getData($contractData, 'VALID_TO', 'DateTime'),
                $this->getData($contractData, 'CUSTOMER', 'string', true),
                $this->getData($contractData, 'CUSTOMER_NAME', 'string'),
                $this->getSapCreationDateTime($contractData['SAP_CREATE_DATE'], $contractData['SAP_CREATE_TIME']),
                $this->getData($contractData, 'STATUS', 'string'),
                $this->getData($contractData, 'STATUS_DESCRIPTION', 'string'),
                $this->getData($contractData, 'DOCUMENT_NET_VALUE', 'float'),
                $this->getData($contractData, 'DOCUMENT_CURRENCY', 'string')
            );

            $contracts->add($contract);
        }

        return $contracts;
    }
}
