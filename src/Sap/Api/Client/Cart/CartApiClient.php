<?php

declare(strict_types=1);

namespace ReiffIntegrations\Sap\Api\Client\Cart;

use Psr\Log\LoggerInterface;
use ReiffIntegrations\Api\Client\AbstractApiClient;
use ReiffIntegrations\Sap\Cart\PriceCartProcessor;
use ReiffIntegrations\Sap\Exception\TimeoutException;
use ReiffIntegrations\Sap\Struct\Price\ItemCollection;
use ReiffIntegrations\Sap\Struct\Price\ItemStruct;
use ReiffIntegrations\Util\Configuration;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class CartApiClient extends AbstractApiClient
{
    private const API_CONTENT_TYPE = 'text/xml';

    public function __construct(
        private readonly SystemConfigService $systemConfigService,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @throws TimeoutException
     */
    public function getPrices(Cart $cart, string $debtorNumber): ItemCollection
    {
        $postData = sprintf('<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:urn="urn:sap-com:document:sap:rfc:functions">
   <soapenv:Header/>
   <soapenv:Body>
      <urn:ZSHOP_SALES_PRICE_SIMULATE>
         <IT_ITEMS>
             %s
         </IT_ITEMS>
         <IV_SESSION_LANGUAGE>D</IV_SESSION_LANGUAGE>
      </urn:ZSHOP_SALES_PRICE_SIMULATE>
   </soapenv:Body>
</soapenv:Envelope>', $this->getItems($cart, $debtorNumber));

        $method = self::METHOD_POST;

        $ignoreSsl = $this->systemConfigService->getBool(Configuration::CONFIG_KEY_API_IGNORE_SSL);
        $url       = $this->systemConfigService->getString(Configuration::CONFIG_KEY_CART_API_URL);

        if (empty($url)) {
            return new ItemCollection();
        }

        $userName = $this->systemConfigService->getString(Configuration::CONFIG_KEY_API_USER_NAME);
        $password = $this->systemConfigService->getString(Configuration::CONFIG_KEY_API_PASSWORD);

        $headerData = [
            'Content-Type: ' . self::API_CONTENT_TYPE . "; charset='utf-8'",
            'Accept: ' . self::API_CONTENT_TYPE,
            'Content-length: ' . strlen($postData),
        ];

        $handle = $this->getCurlHandle($url, $userName, $password, $headerData, $method, $postData, $ignoreSsl);

        $response    = curl_exec($handle);
        $errorNumber = curl_errno($handle);
        $statusCode  = curl_getinfo($handle, CURLINFO_HTTP_CODE);

        curl_close($handle);

        if ($errorNumber !== CURLE_OK || $statusCode !== 200 || $response === false) {
            $this->logRequestError($method, $url, $postData, (string) $response, $errorNumber);

            if ($errorNumber === CURLE_OPERATION_TIMEOUTED) {
                throw new TimeoutException('request timeout');
            }

            return new ItemCollection();
        }

        return $this->getCollection((string) $response);
    }

    private function getItems(Cart $cart, string $debtorNumber): string
    {
        $requests = [];

        foreach ($cart->getLineItems()->filterType(LineItem::PRODUCT_LINE_ITEM_TYPE) as $lineItem) {
            $requests[] = sprintf(
                '<item>
               <KUNNR>%s</KUNNR>
               <MATNR>%s</MATNR>
               <MGAME>%d</MGAME>
               <VRKME></VRKME>
            </item>',
                $debtorNumber,
                $lineItem->getPayloadValue('productNumber'),
                $lineItem->getQuantity()
            );
        }

        return implode("\n", $requests);
    }

    private function getCollection(string $response): ItemCollection
    {
        $items = new ItemCollection();
        $xml   = simplexml_load_string($response);

        if ($xml === false) {
            return $items;
        }

        $xmlOrderLumpSum = $xml->xpath('//ES_HEADER_PRICE');

        if (is_array($xmlOrderLumpSum)) {
            $header      = (array) json_decode((string) json_encode($xmlOrderLumpSum), true);
            $innerHeader = current($header);

            if (is_array($innerHeader) && array_key_exists('ADD_HEADER_PRICE', $innerHeader)) {
                $items->set(PriceCartProcessor::SAP_CART_SHIPPING_ITEM_KEY, new ItemStruct(
                    PriceCartProcessor::SAP_CART_SHIPPING_ITEM_KEY,
                    1,
                    (float) $innerHeader['ADD_HEADER_PRICE'],
                    1,
                    ItemStruct::ORDER_UNIT_SHIPPING
                ));
            }
        }

        $xmlLineItems = $xml->xpath('//ET_FULL_PRICE/item');
        $lineItems    = [];

        if (is_array($xmlLineItems)) {
            $lineItems = (array) json_decode((string) json_encode($xmlLineItems), true);
        }

        /** @var array $lineItem */
        foreach ($lineItems as $lineItem) {
            $quantity      = (int) $lineItem['PRICE']['item']['MGAME'];
            $priceQuantity = (int) $lineItem['PRICE']['item']['KPEIN'];
            $orderUnit     = $lineItem['PRICE']['item']['VRKME'];
            $price         = ((float) $lineItem['PRICE']['item']['NETPR']) / $priceQuantity * $quantity;
            $productNumber = $lineItem['MATNR'];
            $items->set($productNumber, new ItemStruct(
                $productNumber,
                $quantity,
                $price,
                $priceQuantity,
                $orderUnit
            ));
        }

        return $items;
    }

    private function logRequestError(
        string $method,
        string $exportUrl,
        string $serializedData,
        string $response,
        int $errorNumber
    ): void {
        $this->logger->error('API error during prices read', [
            'method'     => $method,
            'requestUrl' => $exportUrl,
            'body'       => $serializedData,
            'response'   => $response,
            'error'      => $errorNumber,
        ]);
    }
}
