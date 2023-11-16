<?php

declare(strict_types=1);

namespace ReiffIntegrations\Sap\Api\Client\Orders;

use DateTimeInterface;
use Psr\Log\LoggerInterface;
use ReiffIntegrations\Api\Client\AbstractApiClient;
use ReiffIntegrations\Sap\DataAbstractionLayer\CustomerExtension;
use ReiffIntegrations\Sap\DataAbstractionLayer\ReiffCustomerEntity;
use ReiffIntegrations\Util\Configuration;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class OrderListApiClient extends AbstractApiClient
{
    private const API_CONTENT_TYPE = 'text/xml';
    private const SAP_CLIENT_NR    = '100';

    public function __construct(
        private readonly SystemConfigService $systemConfigService,
        private readonly LoggerInterface $logger,
        private readonly OrderListResponseParser $responseParser
    ) {
    }

    public function getOrders(
        ReiffCustomerEntity $reiffCustomer,
        DateTimeInterface $fromDate,
        DateTimeInterface $toDate,
        CustomerEntity $shopwareCustomer
    ): OrderListApiResponse
    {
        $template = '
            <soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:urn="urn:sap-com:document:sap:rfc:functions">
            <soapenv:Header/>
                <soapenv:Body>
                    <urn:ZSHOP_LIST_ORDER>
                         <IS_ORDER_LIST_INPUT>
                            <CUSTOMER>%s</CUSTOMER>
                            <SALES_ORGANISATION>%s</SALES_ORGANISATION>
                            <DISTRIBUTION_CHANNEL>10</DISTRIBUTION_CHANNEL>
                            <DIVISION>00</DIVISION>
                            <DATE_FROM>%s</DATE_FROM>
                            <DATE_TO>%s</DATE_TO>
                            <LANGUAGE>%s</LANGUAGE>
                         </IS_ORDER_LIST_INPUT>
                      </urn:ZSHOP_LIST_ORDER>
                </soapenv:Body>
            </soapenv:Envelope>
        ';

        $languageCode = $this->fetchLanguageCode($shopwareCustomer);
        $debtorNumber = $this->fetchDebtorNumber($reiffCustomer);
        $salesOrganisation = $this->fetchSalesOrganisation($reiffCustomer);

        $postData = trim(sprintf(
            $template,
            $debtorNumber,
            $salesOrganisation,
            $fromDate->format('Y-m-d'),
            $toDate->format('Y-m-d'),
            $languageCode
        ));

        $method = self::METHOD_POST;

        $ignoreSsl = $this->systemConfigService->getBool(Configuration::CONFIG_KEY_API_IGNORE_SSL);
        $url       = $this->systemConfigService->getString(Configuration::CONFIG_KEY_ORDERS_API_URL);

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
            $this->logRequestError($method, $url, $postData, (string) $response, $errorNumber);

            return $this->responseParser->parseResponse(false, (string) $response);
        }

        return $this->responseParser->parseResponse(true, (string) $response);
    }

    private function logRequestError(
        string $method,
        string $exportUrl,
        string $serializedData,
        string $response,
        int $errorNumber
    ): void {
        $this->logger->error('API error during orders read', [
            'method'     => $method,
            'requestUrl' => $exportUrl,
            'body'       => $serializedData,
            'response'   => $response,
            'error'      => $errorNumber,
        ]);
    }

    private function fetchLanguageCode(CustomerEntity $shopwareCustomer): string
    {
        $languageCode = $shopwareCustomer->getLanguage()?->getTranslationCode()?->getCode();

        if (null === $languageCode) {
            $languageCode = $this->systemConfigService->getString(Configuration::CONFIG_KEY_API_FALLBACK_LANGUAGE_CODE);
        }

        return $languageCode;
    }

    private function fetchSalesOrganisation(ReiffCustomerEntity $reiffCustomer): string
    {
        $salesOrganisation = $reiffCustomer->getSalesOrganisation();

        if (empty($salesOrganisation)) {
            $salesOrganisation = $this->systemConfigService->getString(
                Configuration::CONFIG_KEY_API_FALLBACK_SALES_ORGANISATION
            );
        }

        return $salesOrganisation;
    }

    private function fetchDebtorNumber(ReiffCustomerEntity $reiffCustomer): string
    {
        $debtorNumber = $reiffCustomer->getDebtorNumber();

        if (empty($debtorNumber)) {
            $debtorNumber = $this->systemConfigService->getString(
                Configuration::CONFIG_KEY_API_FALLBACK_DEBTOR_NUMBER
            );
        }

        return $debtorNumber;
    }
}
