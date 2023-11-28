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
    public function getAvailability(
        array $productNumbers,
        string $salesOrganisation,
        string $languageCode
    ): AvailabilityStructCollection
    {
        $template = '
            <soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:urn="urn:sap-com:document:sap:rfc:functions">
               <soapenv:Header/>
               <soapenv:Body>
                  <urn:ZSHOP_MATERIAL_AVAILABILITY>
                     <IT_ITEMS>
                        %s
                     </IT_ITEMS>
                     <IV_DISTRIBUTION_CHANNEL>10</IV_DISTRIBUTION_CHANNEL>
                     <IV_SALES_ORGANISATION>%s</IV_SALES_ORGANISATION>
                     <IV_SESSION_LANGUAGE>%s</IV_SESSION_LANGUAGE>
                  </urn:ZSHOP_MATERIAL_AVAILABILITY>
               </soapenv:Body>
            </soapenv:Envelope>
        ';

        $postData = trim(sprintf(
            $template,
            $this->getAvailabilityRequest($productNumbers),
            $salesOrganisation,
            $languageCode
        ));

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

            $this->logger->error('API error during availability read', [
                'method'           => $method,
                'requestUrl'       => $url,
                'body'             => $postData,
                'response'         => (string) $response,
                'errorNumber'      => $errorNumber,
                'errorDescription' => $errorDescription,
            ]);

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
            $template = '
                <item>
                    <MATERIAL>%s</MATERIAL>
                    <PLANT></PLANT>
                </item>
            ';

            $requests[] = trim(sprintf(
                $template,
                $productNumber
            ));
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
}
