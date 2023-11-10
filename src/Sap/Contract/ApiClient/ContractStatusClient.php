<?php

declare(strict_types=1);

namespace ReiffIntegrations\Sap\Contract\ApiClient;

use Psr\Log\LoggerInterface;
use ReiffIntegrations\Api\Client\AbstractApiClient;
use ReiffIntegrations\Util\Configuration;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class ContractStatusClient extends AbstractApiClient
{
    private const API_CONTENT_TYPE = 'text/xml';
    private const SAP_CLIENT_NR    = '100';

    public function __construct(
        private readonly SystemConfigService $systemConfigService,
        private readonly LoggerInterface $logger,
        private readonly ContractStatusResponseParser $responseParser
    ) {
    }

    public function getContractStatus(string $contractNumber, Context $context): ContractStatusResponse
    {
        $postData = sprintf('<soapenv:Envelope
                                xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"
                                xmlns:urn="urn:sap-com:document:sap:rfc:functions">
                                   <soapenv:Header/>
                                   <soapenv:Body>
                                      <urn:ZSHOP_CONTRACT_DETAILS>
                                         <I_CUSTOMER_CONTRACT_NUMBER>%s</I_CUSTOMER_CONTRACT_NUMBER>
                                         <I_LANGUAGE>DE</I_LANGUAGE>
                                      </urn:ZSHOP_CONTRACT_DETAILS>
                                   </soapenv:Body>
                                </soapenv:Envelope>', $contractNumber);

        $method = self::METHOD_POST;

        $ignoreSsl = $this->systemConfigService->getBool(Configuration::CONFIG_KEY_API_IGNORE_SSL);
        $url       = $this->systemConfigService->getString(Configuration::CONFIG_KEY_API_CONTRACT_STATUS_URL);

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
            $this->logRequestError($method, $url, $postData, (string) $response, $errorNumber);

            return $this->responseParser->parseResponse(false, (string) $response, $context);
        }

        return $this->responseParser->parseResponse(true, (string) $response, $context);
    }

    private function logRequestError(
        string $method,
        string $exportUrl,
        string $serializedData,
        string $response,
        int $errorNumber
    ): void {
        $this->logger->error('API error during contracts status read', [
            'method'     => $method,
            'requestUrl' => $exportUrl,
            'body'       => $serializedData,
            'response'   => $response,
            'error'      => $errorNumber,
        ]);
    }
}
