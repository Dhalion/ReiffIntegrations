<?php

declare(strict_types=1);

namespace ReiffIntegrations\Sap\Api\Client\Orders;

use Psr\Log\LoggerInterface;
use ReiffIntegrations\Sap\Struct\OrderCollection;
use ReiffIntegrations\Sap\Struct\OrderStruct;

class OrderListResponseParser
{
    private const RETURN_START_STR  = '<ES_RETURN>';
    private const RETURN_END_STR    = '</ES_RETURN>';
    private const RETURN_SHORT_HAND = '<ES_RETURN/>';

    private const LIST_START_STR  = '<ET_ORDER_LIST>';
    private const LIST_END_STR    = '</ET_ORDER_LIST>';
    private const LIST_SHORT_HAND = '<ET_ORDER_LIST/>';

    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    public function parseResponse(bool $success, string $rawResponse): OrderListApiResponse
    {
        $orders = new OrderCollection();

        if (empty($rawResponse)) {
            return new OrderListApiResponse($success, $rawResponse, $orders);
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

            return new OrderListApiResponse($success, $rawResponse, $orders);
        }

        if (count($returnData) === 0) {
            $this->logger->error('Return data array is empty', [
                'xmlReturn' => $xmlReturn,
            ]);

            return new OrderListApiResponse($success, $rawResponse, $orders);
        }

        try {
            $orders = $this->buildOrders($listData);
        } catch (\Exception $exception) {
            $this->logger->error('Error determining the orders', [
                'response' => $rawResponse,
                'error'    => $exception->getMessage(),
                'listData' => $listData,
            ]);
        }

        /** @var null|string $returnMessage */
        $returnMessage = $returnData['MESSAGE'] ?? null;

        return new OrderListApiResponse(
            $success,
            $rawResponse,
            $orders,
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

    private function buildOrders(array $listData): OrderCollection
    {
        $orders = new OrderCollection();

        if (!isset($listData['item']) || !is_array($listData['item']) || count($listData['item']) === 0) {
            return $orders;
        }

        $listDataIterator = new \RecursiveArrayIterator($listData['item']);

        if (!$listDataIterator->hasChildren()) {
            $listData['item'][] = $listData['item'];
        }

        foreach ($listData['item'] as $orderData) {
            if (!is_array($orderData) || empty($orderData)) {
                continue;
            }

            $order = new OrderStruct(
                $orderData['ORDER_NUMBER'] ? (string) $orderData['ORDER_NUMBER'] : null,
                $orderData['ORDER_REFERENCE'] ? (string) $orderData['ORDER_REFERENCE'] : null,
                $orderData['ORDER_DATE'] ? $this->getDateTimeImmutableFromString((string) $orderData['ORDER_DATE']) : null,
                $orderData['CUSTOMER_NAME'] ? (string) $orderData['CUSTOMER_NAME'] : null,
                $orderData['STATUS_DESCRIPTION'] ? (string) $orderData['STATUS_DESCRIPTION'] : null,
                $orderData['DOCUMENT_NET_VALUE'] ? (float) $orderData['DOCUMENT_NET_VALUE'] : null,
                $orderData['DOCUMENT_CURRENCY'] ? (string) $orderData['DOCUMENT_CURRENCY'] : null
            );

            $orders->add($order);
        }

        return $orders;
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
