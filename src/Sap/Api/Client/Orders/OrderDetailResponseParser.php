<?php

declare(strict_types=1);

namespace ReiffIntegrations\Sap\Api\Client\Orders;

use Psr\Log\LoggerInterface;
use ReiffIntegrations\Sap\Struct\OrderAddressCollection;
use ReiffIntegrations\Sap\Struct\OrderAddressStruct;
use ReiffIntegrations\Sap\Struct\OrderDetailStruct;
use ReiffIntegrations\Sap\Struct\OrderDocumentCollection;
use ReiffIntegrations\Sap\Struct\OrderDocumentStruct;
use ReiffIntegrations\Sap\Struct\OrderLineItemCollection;
use ReiffIntegrations\Sap\Struct\OrderLineItemStruct;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\Country\CountryEntity;

class OrderDetailResponseParser
{
    private const RETURN_START_STR  = '<ES_RETURN>';
    private const RETURN_END_STR    = '</ES_RETURN>';
    private const RETURN_SHORT_HAND = '<ES_RETURN/>';

    private const LIST_START_STR  = '<ES_CUSTOMER_ORDER_DETAILS>';
    private const LIST_END_STR    = '</ES_CUSTOMER_ORDER_DETAILS>';
    private const LIST_SHORT_HAND = '<ES_CUSTOMER_ORDER_DETAILS/>';

    /** @var string[] */
    private array $countriesByIsoCode = [];

    public function __construct(private readonly LoggerInterface $logger, private readonly EntityRepository $countryRepository)
    {
    }

    public function parseResponse(bool $success, string $rawResponse, Context $context): OrderDetailApiResponse
    {
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

            return new OrderDetailApiResponse($success, $rawResponse, null);
        }

        if (count($returnData) === 0) {
            $this->logger->error('Return data array is empty', [
                'xmlReturn' => $xmlReturn,
            ]);

            return new OrderDetailApiResponse($success, $rawResponse, null);
        }

        $order = null;

        try {
            $order = $this->buildOrder($listData, $context);
        } catch (\Exception $exception) {
            $this->logger->error('Error determining the order details', [
                'response' => $rawResponse,
                'error'    => $exception->getMessage(),
                'listData' => $listData,
            ]);
        }

        /** @var null|string $returnMessage */
        $returnMessage = $returnData['MESSAGE'] ?? null;

        return new OrderDetailApiResponse(
            $success,
            $rawResponse,
            $order,
            $returnMessage ? (string) $returnMessage : null,
        );
    }

    private function getStringPart(string $content, string $start, string $end): string
    {
        $content = ' ' . $content;
        $ini     = strpos($content, $start);

        if ($ini === 0) {
            return '';
        }
        $ini += strlen($start);
        $len = strpos($content, $end, $ini) - $ini;

        return substr($content, $ini, $len);
    }

    private function buildOrder(array $orderData, Context $context): ?OrderDetailStruct
    {
        if (!isset($orderData['ORDER_NUMBER'])) {
            return null;
        }

        if (!is_array($orderData['ORDER_HEADER_DATA']) || !is_array($orderData['ORDER_ITEM_DATA']) || !is_array($orderData['ORDER_FLOW'])) {
            return null;
        }

        if (count($orderData['ORDER_ITEM_DATA']) === 0) {
            return null;
        }

        $lineItemDataIterator = new \RecursiveArrayIterator($orderData['ORDER_ITEM_DATA']['item']);

        if (!$lineItemDataIterator->hasChildren()) {
            $orderData['ORDER_ITEM_DATA']['item'] = [$orderData['ORDER_ITEM_DATA']['item']];
        }

        $flowDataIterator = new \RecursiveArrayIterator($orderData['ORDER_FLOW']['FLOW_DATA']['item']);

        if (!$flowDataIterator->hasChildren()) {
            $orderData['ORDER_FLOW']['FLOW_DATA']['item'] = [$orderData['ORDER_FLOW']['FLOW_DATA']['item']];
        }

        return new OrderDetailStruct(
            $orderData['ORDER_NUMBER'] ? (string) $orderData['ORDER_NUMBER'] : null,
            // TODO: Add helper for array access with type safety, e.g. getData($orderData, 'ORDER_HEADER_DATA.BUSINESS_DATA.ORDER_REFERENCE', null)
            $orderData['ORDER_HEADER_DATA']['BUSINESS_DATA']['ORDER_REFERENCE'] ? (string) $orderData['ORDER_HEADER_DATA']['BUSINESS_DATA']['ORDER_REFERENCE'] : null,
            $orderData['ORDER_HEADER_DATA']['BUSINESS_DATA']['ORDER_DATE'] ? $this->getDateTimeImmutableFromString((string) $orderData['ORDER_HEADER_DATA']['BUSINESS_DATA']['ORDER_DATE']) : null,
            $orderData['ORDER_HEADER_DATA']['BUSINESS_DATA']['CUSTOMER_NAME'] ? (string) $orderData['ORDER_HEADER_DATA']['BUSINESS_DATA']['CUSTOMER_NAME'] : null,
            $orderData['ORDER_HEADER_DATA']['BUSINESS_DATA']['CUSTOMER'] ? (string) ltrim($orderData['ORDER_HEADER_DATA']['BUSINESS_DATA']['CUSTOMER'], '0') : null,
            $orderData['ORDER_HEADER_DATA']['BUSINESS_DATA']['STATUS_DESCRIPTION'] ? (string) $orderData['ORDER_HEADER_DATA']['BUSINESS_DATA']['STATUS_DESCRIPTION'] : null,
            $orderData['ORDER_HEADER_DATA']['BUSINESS_DATA']['DOCUMENT_NET_VALUE'] ? (float) $orderData['ORDER_HEADER_DATA']['BUSINESS_DATA']['DOCUMENT_NET_VALUE'] : null,
            $orderData['ORDER_HEADER_DATA']['BUSINESS_DATA']['DOCUMENT_CURRENCY'] ? (string) $orderData['ORDER_HEADER_DATA']['BUSINESS_DATA']['DOCUMENT_CURRENCY'] : null,
            $orderData['ORDER_HEADER_DATA']['BUSINESS_DATA']['DOCUMENT_FREIGHT_COST'] ? (float) $orderData['ORDER_HEADER_DATA']['BUSINESS_DATA']['DOCUMENT_FREIGHT_COST'] : null,
            $orderData['ORDER_HEADER_DATA']['BUSINESS_DATA']['DOCUMENT_EXTRA_HEADER_COST'] ? (float) $orderData['ORDER_HEADER_DATA']['BUSINESS_DATA']['DOCUMENT_EXTRA_HEADER_COST'] : null,
            $this->getAddresses($orderData['ORDER_HEADER_DATA']['PARTNER_ADDRESS'], $context),
            $this->getLineItems($orderData['ORDER_ITEM_DATA']['item']),
            $this->getDocuments($orderData['ORDER_FLOW']['FLOW_DATA']['item']),
        );
    }

    private function getAddresses(array $addressesData, Context $context): OrderAddressCollection
    {
        $addresses = new OrderAddressCollection();

        foreach (OrderAddressStruct::ADDRESS_TYPES as $addressType) {
            $addressData = $addressesData[$addressType];

            $address = new OrderAddressStruct(
                $addressType,
                $addressData['NAME'],
                $addressData['STREET'],
                $addressData['POSTL_CODE'],
                $addressData['CITY'],
                $this->getCountryName($addressData['COUNTRYISO'], $context),
            );

            $addresses->add($address);
        }

        return $addresses;
    }

    private function getLineItems(array $lineItemsData): OrderLineItemCollection
    {
        $lineItems = new OrderLineItemCollection();

        foreach ($lineItemsData as $lineItemData) {
            $lineItem = new OrderLineItemStruct(
                $lineItemData['MATERIAL_NUMBER'],
                is_array($lineItemData['CUSTOMER_MATERIAL_NUMBER']) ? null : $lineItemData['CUSTOMER_MATERIAL_NUMBER'],
                $lineItemData['SHORT_TEXT'],
                $lineItemData['SALES_UNIT_ISO'],
                (float) $lineItemData['ORDER_QUANTITY'],
                (float) $lineItemData['NET_VALUE'],
            );

            $lineItems->add($lineItem);
        }

        return $lineItems;
    }

    private function getDocuments(array $documentsData): OrderDocumentCollection
    {
        $documents = new OrderDocumentCollection();

        foreach ($documentsData as $documentData) {
            if (!empty($documentData['INVOICE'])) {
                $documentDate = \DateTimeImmutable::createFromFormat('Y-m-d', $documentData['INVOICE_CREATION_DATE']);

                if (!$documentDate) {
                    throw new \RuntimeException(sprintf('Invalid document date for invoice %s', $documentData['INVOICE']));
                }

                $invoice = new OrderDocumentStruct(
                    $documentData['INVOICE'],
                    OrderDocumentCollection::DOCUMENT_TYPE_INVOICE,
                    $documentDate,
                );

                $documents->add($invoice);
            }

            if (!empty($documentData['DELIVERY'])) {
                $documentDate = \DateTimeImmutable::createFromFormat('Y-m-d', $documentData['DELIVERY_CREATION_DATE']);

                if (!$documentDate) {
                    throw new \RuntimeException(sprintf('Invalid document date for delivery %s', $documentData['DELIVERY']));
                }

                $trackingUrls = [];

                if (is_array($documentData['DELIVERY_TRACKING']) && array_key_exists('item', $documentData['DELIVERY_TRACKING'])) {
                    $trackingDataIterator = new \RecursiveArrayIterator($documentData['DELIVERY_TRACKING']['item']);

                    if (!$trackingDataIterator->hasChildren()) {
                        $documentData['DELIVERY_TRACKING']['item'] = [$documentData['DELIVERY_TRACKING']['item']];
                    }

                    foreach ($documentData['DELIVERY_TRACKING']['item'] as $trackingLink) {
                        $trackingUrls[$trackingLink['EXIDV2']] = urldecode($trackingLink['TRACK_LINK']);
                    }
                }

                $delivery = new OrderDocumentStruct(
                    $documentData['DELIVERY'],
                    OrderDocumentCollection::DOCUMENT_TYPE_DELIVERY,
                    $documentDate,
                    $trackingUrls,
                );

                $documents->add($delivery);
            }
        }

        return $documents;
    }

    private function getCountryName(string $isoCode, Context $context): string
    {
        if (!array_key_exists($isoCode, $this->countriesByIsoCode)) {
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('iso', $isoCode));

            /** @var null|CountryEntity $country */
            $country = $this->countryRepository->search($criteria, $context)->first();

            if ($country) {
                $this->countriesByIsoCode[$isoCode] = (string) $country->getName();
            }
        }

        return array_key_exists($isoCode, $this->countriesByIsoCode) ? $this->countriesByIsoCode[$isoCode] : $isoCode;
    }

    private function getDateTimeImmutableFromString(string $dateString): ?\DateTimeImmutable
    {
        $timezone = new \DateTimeZone(ini_get('date.timezone') ?: 'UTC');
        $date     = \DateTimeImmutable::createFromFormat(
            'Y-m-d H:i:s',
            $dateString . ' 00:00:00',
            $timezone
        );

        return ($date instanceof \DateTimeImmutable) ? $date : null;
    }
}
