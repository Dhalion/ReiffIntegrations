<?php

declare(strict_types=1);

namespace ReiffIntegrations\Sap\Offer\ApiClient;

use Psr\Log\LoggerInterface;
use ReiffIntegrations\Api\Client\AbstractApiClient;
use ReiffIntegrations\Sap\DataAbstractionLayer\ReiffCustomerEntity;
use ReiffIntegrations\Util\Configuration;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class OfferReadApiClient extends AbstractApiClient
{
    private const API_CONTENT_TYPE = 'text/xml';
    private const SAP_CLIENT_NR    = '100';

    protected SystemConfigService $systemConfigService;

    private LoggerInterface $logger;
    private OfferResponseParser $responseParser;

    public function __construct(
        SystemConfigService $systemConfigService,
        LoggerInterface $logger,
        OfferResponseParser $responseParser
    ) {
        $this->systemConfigService = $systemConfigService;
        $this->logger              = $logger;
        $this->responseParser      = $responseParser;
    }

    public function readOffers(ReiffCustomerEntity $reiffCustomer): OfferReadApiResponse
    {
        if (empty($reiffCustomer->getDebtorNumber())) {
            return $this->responseParser->parseResponse(false, '');
        }

        $template = '
            <soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:urn="urn:sap-com:document:sap:rfc:functions">
                <soapenv:Header/>
                <soapenv:Body>
                    <urn:ZSHOP_LIST_QUOTATION>
                        <IS_QUOTATION_LIST_INPUT>
                            <CUSTOMER>%s</CUSTOMER>
                            <SALES_ORGANISATION>%s</SALES_ORGANISATION>
                            <DISTRIBUTION_CHANNEL>10</DISTRIBUTION_CHANNEL>
                            <DIVISION>00</DIVISION>
                        </IS_QUOTATION_LIST_INPUT>
                    </urn:ZSHOP_LIST_QUOTATION>
                </soapenv:Body>
            </soapenv:Envelope>
        ';

        $postData = sprintf(
            $template,
            $reiffCustomer->getDebtorNumber(),
            $this->fetchSalesOrganisation($reiffCustomer)
        );

        $method    = self::METHOD_POST;
        $ignoreSsl = $this->systemConfigService->getBool(Configuration::CONFIG_KEY_API_IGNORE_SSL);
        $url       = $this->systemConfigService->getString(Configuration::CONFIG_KEY_OFFER_API_URL);

        if (empty($url)) {
            return $this->responseParser->parseResponse(false, '');
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
            $this->logger->error('API error during offers read', [
                'method'     => $method,
                'requestUrl' => $url,
                'body'       => $postData,
                'response'   => (string) $response,
                'error'      => $errorNumber,
            ]);

            return $this->responseParser->parseResponse(false, (string) $response);
        }

        return $this->responseParser->parseResponse(true, (string) $response);
    }

    private function fetchSalesOrganisation(ReiffCustomerEntity $reiffCustomer): string
    {
        $salesOrganisation = $reiffCustomer->getSalesOrganisation();

        if (empty($salesOrganisation) || $salesOrganisation === '-') {
            $salesOrganisation = $this->systemConfigService->getString(
                Configuration::CONFIG_KEY_API_FALLBACK_SALES_ORGANISATION
            );
        }

        return $salesOrganisation;
    }
}
