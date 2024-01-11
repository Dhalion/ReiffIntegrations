<?php

declare(strict_types=1);

namespace ReiffIntegrations\Sap\ShopPricing\ApiClient;

use Psr\Log\LoggerInterface;
use ReiffIntegrations\Api\Client\AbstractApiClient;
use ReiffIntegrations\Sap\Exception\TimeoutException;
use ReiffIntegrations\Sap\Struct\Price\ItemCollection;
use ReiffIntegrations\Sap\Struct\Price\ItemStruct;
use ReiffIntegrations\Util\Configuration;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class PriceApiClient extends AbstractApiClient
{
    private const API_CONTENT_TYPE       = 'text/xml';
    private const API_TIMEOUT_IN_SECONDS = 30;
    private const PRICE_QUANTITY         = 1;

    public function __construct(
        private readonly SystemConfigService $systemConfigService,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function getPrices(
        string $debtorNumber,
        string $salesOrganisation,
        string $languageCode,
        array $productNumbers,
    ): ItemCollection {
        $template = '
            <soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:urn="urn:sap-com:document:sap:soap:functions:mc-style">
               <soapenv:Header/>
               <soapenv:Body>
                  <urn:ZshopGetMaterialFullPrice>
                     <ItItems>
                        %s
                     </ItItems>
                     <IvDistributionChannel>10</IvDistributionChannel>
                     <IvDivision>00</IvDivision>
                     <IvSalesOrganisation>%s</IvSalesOrganisation>
                     <IvSessionLanguage>%s</IvSessionLanguage>
                  </urn:ZshopGetMaterialFullPrice>
               </soapenv:Body>
            </soapenv:Envelope>
        ';

        $postData = trim(sprintf(
            $template,
            $this->getPriceRequests($productNumbers, $debtorNumber),
            $salesOrganisation,
            $languageCode
        ));

        $method = self::METHOD_POST;

        $ignoreSsl = $this->systemConfigService->getBool(Configuration::CONFIG_KEY_API_IGNORE_SSL);
        $url       = $this->systemConfigService->getString(Configuration::CONFIG_KEY_PRICE_API_URL);

        if (empty($url)) {
            return new ItemCollection();
        }

        $headerData = [
            'Content-Type: ' . self::API_CONTENT_TYPE,
            'Accept: ' . self::API_CONTENT_TYPE,
            'Content-length: ' . strlen($postData),
        ];

        $username = $this->systemConfigService->getString(Configuration::CONFIG_KEY_API_USER_NAME);
        $password = $this->systemConfigService->getString(Configuration::CONFIG_KEY_API_PASSWORD);

        $handle = $this->getCurlHandle(
            $url,
            $username,
            $password,
            $headerData,
            $method,
            $postData,
            $ignoreSsl,
            self::API_TIMEOUT_IN_SECONDS
        );

        $response         = curl_exec($handle);
        $errorNumber      = curl_errno($handle);
        $errorDescription = curl_error($handle);
        $statusCode       = curl_getinfo($handle, CURLINFO_HTTP_CODE);

        curl_close($handle);

        if ($errorNumber !== CURLE_OK || $statusCode !== 200 || $response === false) {
            $this->logger->error('API error during prices read', [
                'method'           => $method,
                'requestUrl'       => $url,
                'body'             => $postData,
                'response'         => (string) $response,
                'errorNumber'      => $errorNumber,
                'errorDescription' => $errorDescription,
            ]);

            throw new \RuntimeException('API error during prices read');
        }

        return $this->getCollection((string) $response);
    }

    private function getPriceRequests(array $productNumbers, string $debtorNumber): string
    {
        $requests = [];

        foreach ($productNumbers as $productNumber) {
            $template = '
                <item>
                    <Kunnr>%s</Kunnr>
                    <Matnr>%s</Matnr>
                    <Mgame>%s</Mgame>
                    <Vrkme></Vrkme>
                    <DatumPrice></DatumPrice>
                </item>
            ';

            $requests[] = trim(sprintf(
                $template,
                $debtorNumber,
                $productNumber,
                self::PRICE_QUANTITY
            ));
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

        $products = $xml->xpath('//EtFullPrice/item');

        foreach ($products as $product) {
            foreach ($product->Price->item as $price) {
                $productNumber = (string) $product->Matnr;
                $priceQuantity = (int) $price->Kpein;
                $orderUnit     = (string) $price->Vrkme;
                $quantity      = (int) $price->Mgame;
                $price         = ((float) $price->Netpr) / $priceQuantity * $quantity;

                $items->set(
                    sprintf(ItemCollection::ITEM_KEY_HANDLE, $productNumber, $quantity),
                    new ItemStruct($productNumber, $quantity, $price, $priceQuantity, $orderUnit)
                );

                break;
            }
        }

        return $items;
    }
}
