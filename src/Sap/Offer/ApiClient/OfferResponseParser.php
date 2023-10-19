<?php

declare(strict_types=1);

namespace ReiffIntegrations\Sap\Offer\ApiClient;

use Psr\Log\LoggerInterface;
use ReiffIntegrations\Sap\Struct\OfferDocumentCollection;
use ReiffIntegrations\Sap\Struct\OfferDocumentPositionCollection;
use ReiffIntegrations\Sap\Struct\OfferDocumentPositionStruct;
use ReiffIntegrations\Sap\Struct\OfferDocumentStruct;
use ReiffIntegrations\Sap\Util\AbstractResponseParser;

class OfferResponseParser extends AbstractResponseParser
{
    private const RETURN_START_STR  = '<ES_RETURN>';
    private const RETURN_END_STR    = '</ES_RETURN>';
    private const RETURN_SHORT_HAND = '<ES_RETURN/>';

    private const LIST_START_STR  = '<ET_QUOTATION_LIST>';
    private const LIST_END_STR    = '</ET_QUOTATION_LIST>';
    private const LIST_SHORT_HAND = '<ET_QUOTATION_LIST/>';

    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function parseResponse(bool $success, string $rawResponse): OfferReadApiResponse
    {
        $documents = new OfferDocumentCollection();

        if (empty($rawResponse) || !$success) {
            return new OfferReadApiResponse($success, $rawResponse, $documents);
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

            return new OfferReadApiResponse($success, $rawResponse, $documents);
        }

        if (count($returnData) === 0) {
            $this->logger->error('Return data array is empty', [
                'xmlReturn' => $xmlReturn,
            ]);

            return new OfferReadApiResponse($success, $rawResponse, $documents);
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

        return new OfferReadApiResponse(
            $success,
            $rawResponse,
            $documents,
            $returnMessage ? (string) $returnMessage : null,
        );
    }

    private function buildDocuments(array $listData): OfferDocumentCollection
    {
        $documents = new OfferDocumentCollection();

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

            if (!isset($documentData['ITEMS'])) {
                $documentData['ITEMS'] = [];
            }

            if (!is_array($documentData['ITEMS'])) {
                $documentData['ITEMS'] = [];
            }

            $document = new OfferDocumentStruct(
                $this->buildDocumentPositions($documentData['ITEMS']),
                $this->getData($documentData, 'DOCUMENT_NUMBER', 'string', true),
                $this->getData($documentData, 'REFERENCE'),
                $this->getData($documentData, 'VALID_TO', 'DateTime'),
                $this->getData($documentData, 'TYPE'),
                $this->getData($documentData, 'ORDER_FEE', 'float'),
                $this->getData($documentData, 'ADDITIONAL_COSTS', 'float'),
                $this->getData($documentData, 'CURRENCY'),
            );

            $documents->add($document);
        }

        return $documents;
    }

    private function buildDocumentPositions(array $itemsData): OfferDocumentPositionCollection
    {
        $positions = new OfferDocumentPositionCollection();

        if (!isset($itemsData['item'])) {
            return $positions;
        }

        if (!is_array($itemsData['item'])) {
            return $positions;
        }

        if (count($itemsData['item']) === 0) {
            return $positions;
        }

        $itemsIterator = new \RecursiveArrayIterator($itemsData['item']);

        if (!$itemsIterator->hasChildren()) {
            $itemsData['item'][] = $itemsData['item'];
        }

        foreach ($itemsData['item'] as $itemData) {
            if (!is_array($itemData) || empty($itemData)) {
                continue;
            }

            $position = new OfferDocumentPositionStruct(
                $this->getData($itemData, 'POSITION_NO', 'string', true),
                $this->getData($itemData, 'ITEM_NO', 'string', true),
                $this->getData($itemData, 'DESCRIPTION'),
                $this->getData($itemData, 'ORDER_QUANTITY', 'float'),
                $this->getData($itemData, 'ORDER_UOM'),
                $this->getData($itemData, 'ITEM_PRICE', 'float'),
                $this->getData($itemData, 'PRICE_UNIT', 'float'),
                $this->getData($itemData, 'PRICE_UOM'),
                $this->getData($itemData, 'ITEM_VALUE', 'float'),
                $this->getData($itemData, 'CURRENCY'),
                $this->getData($itemData, 'NUMERATOR', 'int'),
                $this->getData($itemData, 'NUMERATOR_UOM'),
                $this->getData($itemData, 'DENOMINATOR', 'int'),
                $this->getData($itemData, 'DENOMINATOR_UOM'),
            );

            $positions->add($position);
        }

        return $positions;
    }
}
