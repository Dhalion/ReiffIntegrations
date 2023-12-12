<?php

declare(strict_types=1);

namespace ReiffIntegrations\Sap\Api\Client\Orders;

use Psr\Log\LoggerInterface;
use ReiffIntegrations\Api\Client\AbstractApiClient;
use ReiffIntegrations\Util\Configuration;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class OrderDetailApiClient extends AbstractApiClient
{
    private const API_CONTENT_TYPE = 'text/xml';
    private const SAP_CLIENT_NR    = '100';

    public function __construct(
        private readonly SystemConfigService $systemConfigService,
        private readonly LoggerInterface $logger,
        private readonly OrderDetailResponseParser $responseParser
    ) {
    }

    public function getOrder(string $orderNumber, Context $context, string $languageCode): OrderDetailApiResponse
    {
        $template = '
            <soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:urn="urn:sap-com:document:sap:rfc:functions">
                <soapenv:Header/>
                <soapenv:Body>
                    <urn:ZSHOP_ORDER_DETAILS>
                    <IV_CUSTOMER_ORDER_NUMBER>%s</IV_CUSTOMER_ORDER_NUMBER>
                    <IV_LANGUAGE>%s</IV_LANGUAGE>
                    </urn:ZSHOP_ORDER_DETAILS>
                </soapenv:Body>
            </soapenv:Envelope>
        ';

        $postData = sprintf(
            $template,
            $orderNumber,
            $languageCode
        );

        $method = self::METHOD_POST;

        $ignoreSsl = $this->systemConfigService->getBool(Configuration::CONFIG_KEY_API_IGNORE_SSL);
        $url       = $this->systemConfigService->getString(Configuration::CONFIG_KEY_ORDER_DETAILS_API_URL);

        if (empty($url)) {
            return $this->responseParser->parseResponse(false, '', $context);
        }

        $username = $this->systemConfigService->getString(Configuration::CONFIG_KEY_API_USER_NAME);
        $password = $this->systemConfigService->getString(Configuration::CONFIG_KEY_API_PASSWORD);

        $headerData = [
            'Content-Type: ' . self::API_CONTENT_TYPE . "; charset='utf-8'",
            'Accept: ' . self::API_CONTENT_TYPE,
            'Content-length: ' . strlen($postData),
        ];

        $handle = $this->getCurlHandle($url, $username, $password, $headerData, $method, $postData, $ignoreSsl);

        curl_setopt($handle, CURLOPT_COOKIE, 'sap-usercontext=sap-client%3D' . self::SAP_CLIENT_NR);

        $response    = curl_exec($handle);
        $errorNumber = curl_errno($handle);
        $statusCode  = curl_getinfo($handle, CURLINFO_HTTP_CODE);

        curl_close($handle);

        if ($errorNumber !== 0 || $statusCode !== 200 || $response === false) {
            $this->logger->error('API error during order detail read', [
                'method'     => $method,
                'requestUrl' => $url,
                'body'       => $postData,
                'response'   => (string) $response,
                'error'      => $errorNumber,
            ]);

            return $this->responseParser->parseResponse(false, (string) $response, $context);
        }

        return $this->responseParser->parseResponse(true, (string) $response, $context);
    }
}
