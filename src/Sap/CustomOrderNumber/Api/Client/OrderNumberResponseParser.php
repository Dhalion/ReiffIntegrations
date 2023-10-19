<?php

declare(strict_types=1);

namespace ReiffIntegrations\Sap\CustomOrderNumber\Api\Client;

use Psr\Log\LoggerInterface;
use ReiffIntegrations\Sap\CustomOrderNumber\Struct\OrderNumberCollection;
use ReiffIntegrations\Sap\CustomOrderNumber\Struct\OrderNumberStruct;

class OrderNumberResponseParser
{
    private const RETURN_START_STR  = '<ES_RETURN>';
    private const RETURN_END_STR    = '</ES_RETURN>';
    private const RETURN_SHORT_HAND = '<ES_RETURN/>';

    private const LIST_START_STR  = '<ET_CUSTOMER_MATERIAL_LIST>';
    private const LIST_END_STR    = '</ET_CUSTOMER_MATERIAL_LIST>';
    private const LIST_SHORT_HAND = '<ET_CUSTOMER_MATERIAL_LIST/>';

    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function parseResponse(bool $success, string $rawResponse): OrderNumberApiResponse
    {
        $documents = new OrderNumberCollection();

        if (empty($rawResponse) || !$success) {
            return new OrderNumberApiResponse($success, $rawResponse, $documents);
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
            $this->logger->error('The XML response cannot be parsed', [
                'response' => $rawResponse,
                'error'    => $exception->getMessage(),
            ]);

            return new OrderNumberApiResponse($success, $rawResponse, $documents);
        }

        if (count($returnData) === 0) {
            $this->logger->error('Return data array is empty', [
                'xmlReturn' => $xmlReturn,
            ]);

            return new OrderNumberApiResponse($success, $rawResponse, $documents);
        }

        try {
            $documents = $this->buildDocuments($listData);
        } catch (\Exception $exception) {
            $this->logger->error('Error determining the documents', [
                'response' => $rawResponse,
                'error'    => $exception->getMessage(),
                'listData' => $listData,
            ]);
        }

        /** @var null|string $returnMessage */
        $returnMessage = $returnData['MESSAGE'] ?? null;

        return new OrderNumberApiResponse(
            $success,
            $rawResponse,
            $documents,
            $returnMessage ? (string) $returnMessage : null,
        );
    }

    private function getStringPart(string $content, string $start, string $end): string
    {
        $content = ' ' . $content;
        $ini     = strpos($content, $start);

        if (empty($ini)) {
            return '';
        }
        $ini += strlen($start);
        $len = strpos($content, $end, $ini) - $ini;

        return substr($content, $ini, $len);
    }

    private function buildDocuments(array $listData): OrderNumberCollection
    {
        $documents = new OrderNumberCollection();

        if (!isset($listData['item'])) {
            return $documents;
        }

        if (!is_array($listData['item'])) {
            return $documents;
        }

        if (count($listData['item']) === 0) {
            return $documents;
        }

        $listDataIterator = new \RecursiveArrayIterator($listData['item']);

        if (!$listDataIterator->hasChildren()) {
            $listData['item'][] = $listData['item'];
        }

        foreach ($listData['item'] as $documentData) {
            if (!is_array($documentData) || empty($documentData)) {
                continue;
            }

            $documents->add(new OrderNumberStruct(
                $documentData['SAP_MATERIAL_NUMBER'],
                $documentData['CUSTOMER_MATERIAL_NUMBER']
            ));
        }

        return $documents;
    }
}
