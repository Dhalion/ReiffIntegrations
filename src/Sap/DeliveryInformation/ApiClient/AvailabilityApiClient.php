<?php

declare(strict_types=1);

namespace ReiffIntegrations\Sap\DeliveryInformation\ApiClient;

use Psr\Log\LoggerInterface;
use ReiffIntegrations\Api\Client\AbstractApiClient;
use ReiffIntegrations\Sap\DeliveryInformation\Struct\AvailabilityStruct;
use ReiffIntegrations\Sap\DeliveryInformation\Struct\AvailabilityStructCollection;
use ReiffIntegrations\Sap\Exception\TimeoutException;
use ReiffIntegrations\Util\Configuration;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class AvailabilityApiClient extends AbstractApiClient
{
    public const INVALID_CODE  = 999;
    public const INVALID_PLANT = 'NONE';

    private const API_CONTENT_TYPE       = 'text/xml';
    private const API_TIMEOUT_IN_SECONDS = 30;

    public function __construct(
        private readonly SystemConfigService $systemConfigService,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @throws TimeoutException
     */
    public function getAvailability(array $productNumbers): AvailabilityStructCollection
    {
        $postData = sprintf(
            '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:itb="http://www.itb-web.de/">
           <soapenv:Header/>
           <soapenv:Body>
              <itb:GetAvailability>
                 %s
              </itb:GetAvailability>
           </soapenv:Body>
        </soapenv:Envelope>',
            $this->getAvailabilityRequest($productNumbers)
        );

        $method = self::METHOD_POST;

        $ignoreSsl = $this->systemConfigService->getBool(Configuration::CONFIG_KEY_API_IGNORE_SSL);
        $url       = $this->systemConfigService->getString(Configuration::CONFIG_KEY_AVAILABILITY_API_URL);

        if (empty($url)) {
            return new AvailabilityStructCollection();
        }

        $headerData = [
            'Content-Type: ' . self::API_CONTENT_TYPE,
            'Accept: ' . self::API_CONTENT_TYPE,
            'Content-length: ' . strlen($postData),
        ];

        $handle = $this->getCurlHandle($url, '', '', $headerData, $method, $postData, $ignoreSsl, self::API_TIMEOUT_IN_SECONDS);

        $response         = curl_exec($handle);
        $errorNumber      = curl_errno($handle);
        $errorDescription = curl_error($handle);
        $statusCode       = curl_getinfo($handle, CURLINFO_HTTP_CODE);

        curl_close($handle);

        if ($errorNumber !== CURLE_OK || $statusCode !== 200 || $response === false) {
            $this->logRequestError($method, $url, $postData, (string) $response, $errorNumber, $errorDescription);

            if ($errorNumber === CURLE_OPERATION_TIMEOUTED) {
                throw new TimeoutException('request timeout');
            }

            return new AvailabilityStructCollection();
        }

        return $this->getCollection((string) $response);
    }

    private function getAvailabilityRequest(array $productNumbers): string
    {
        $requests = [];

        foreach ($productNumbers as $productNumber) {
            $requests[] = sprintf(
                '<GetAvailabilityRequestInformation>
                            <MATERIAL>%s</MATERIAL>
                            <PLANT></PLANT>
                        </GetAvailabilityRequestInformation>',
                $productNumber
            );
        }

        return implode("\n", $requests);
    }

    private function getCollection(string $response): AvailabilityStructCollection
    {
        $items = new AvailabilityStructCollection();
        $xml   = simplexml_load_string($response);

        if ($xml === false) {
            return $items;
        }

        $xmlProducts = $xml->xpath('//GetAvailabilityResponse');
        $products    = [];

        if (is_array($xmlProducts)) {
            $products = (array) json_decode((string) json_encode($xmlProducts), true);
        }

        /** @var array $product */
        foreach ($products as $product) {
            if (!array_key_exists('MATERIAL', $product)) {
                continue;
            }

            $items->add(
                new AvailabilityStruct(
                    !empty($product['MATERIAL']) ? $product['MATERIAL'] : '',
                    !empty($product['PLANT']) ? $product['PLANT'] : self::INVALID_PLANT,
                    !empty($product['QUANTITY']) ? (float) str_replace([','], ['.'], $product['QUANTITY']) : 0.0,
                    !empty($product['UOM']) ? $product['UOM'] : '',
                    !empty($product['CODE']) ? (int) $product['CODE'] : self::INVALID_CODE
                )
            );
        }

        return $items;
    }

    private function logRequestError(
        string $method,
        string $exportUrl,
        string $serializedData,
        string $response,
        int $errorNumber,
        string $errorDescription,
    ): void {
        $this->logger->error('API error during availability read', [
            'method'           => $method,
            'requestUrl'       => $exportUrl,
            'body'             => $serializedData,
            'response'         => $response,
            'errorNumber'      => $errorNumber,
            'errorDescription' => $errorDescription,
        ]);
    }
}
