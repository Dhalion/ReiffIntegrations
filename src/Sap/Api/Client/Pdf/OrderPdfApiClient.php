<?php

declare(strict_types=1);

namespace ReiffIntegrations\Sap\Api\Client\Pdf;

use Psr\Log\LoggerInterface;
use ReiffIntegrations\Api\Client\AbstractApiClient;
use ReiffIntegrations\Sap\Controller\Storefront\OrdersController;
use ReiffIntegrations\Util\Configuration;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class OrderPdfApiClient extends AbstractApiClient
{
    private const API_CONTENT_TYPE = 'text/xml';

    public function __construct(
        private readonly SystemConfigService $systemConfigService,
        private readonly LoggerInterface $logger,
        private readonly OrderPdfResponseParser $responseParser
    ) {
    }

    public function getInvoicePdf(string $documentNumber, CustomerEntity $shopwareCustomer, Context $context): OrderPdfApiResponse
    {
        $languageCode = $this->fetchLanguageCode($shopwareCustomer);

        $postData = sprintf('
            <soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:urn="urn:sap-com:document:sap:rfc:functions">
                <soapenv:Header/>
                <soapenv:Body>
                    <urn:ZSHOP_PDF_INV>
                         <IV_DOCUMENT_NUMBER>%s</IV_DOCUMENT_NUMBER>
                         <IV_SESSION_LANGUAGE>%s</IV_SESSION_LANGUAGE>
                    </urn:ZSHOP_PDF_INV>
                </soapenv:Body>
            </soapenv:Envelope>
                        ', $documentNumber, $languageCode);

        $method = self::METHOD_POST;

        $ignoreSsl = $this->systemConfigService->getBool(Configuration::CONFIG_KEY_API_IGNORE_SSL);
        $url       = $this->systemConfigService->getString(Configuration::CONFIG_KEY_INVOICE_PDF_API_URL);

        if (empty($url)) {
            return $this->responseParser->parseResponse(false, '', OrdersController::DOCUMENT_TYPE_INVOICE);
        }

        $headerData = [
            'Content-Type: ' . self::API_CONTENT_TYPE,
            'Accept: ' . self::API_CONTENT_TYPE,
            'Content-length: ' . strlen($postData),
        ];

        $username = $this->systemConfigService->getString(Configuration::CONFIG_KEY_API_USER_NAME);
        $password = $this->systemConfigService->getString(Configuration::CONFIG_KEY_API_PASSWORD);

        $handle = $this->getCurlHandle($url, $username, $password, $headerData, $method, $postData, $ignoreSsl);

        $response    = curl_exec($handle);
        $errorNumber = curl_errno($handle);
        $statusCode  = curl_getinfo($handle, CURLINFO_HTTP_CODE);

        curl_close($handle);

        if ($errorNumber !== 0 || $statusCode !== 200 || $response === false) {
            $this->logger->error('API error during order PDF read', [
                'method'     => $method,
                'requestUrl' => $url,
                'body'       => $postData,
                'response'   => (string) $response,
                'error'      => $errorNumber,
            ]);

            return $this->responseParser->parseResponse(false, (string) $response, OrdersController::DOCUMENT_TYPE_INVOICE);
        }

        return $this->responseParser->parseResponse(true, (string) $response, OrdersController::DOCUMENT_TYPE_INVOICE);
    }

    public function getDeliveryPdf(string $documentNumber, CustomerEntity $shopwareCustomer, Context $context): OrderPdfApiResponse
    {
        $languageCode = $this->fetchLanguageCode($shopwareCustomer);

        $postData = sprintf('
            <soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:urn="urn:sap-com:document:sap:rfc:functions">
                <soapenv:Header/>
                <soapenv:Body>
                 <urn:ZSHOP_PDF_DEL>
                    <IV_DOCUMENT_NUMBER>%s</IV_DOCUMENT_NUMBER>
                    <IV_SESSION_LANGUAGE>%s</IV_SESSION_LANGUAGE>
                 </urn:ZSHOP_PDF_DEL>
                </soapenv:Body>
            </soapenv:Envelope>
                        ', $documentNumber, $languageCode);

        $method = self::METHOD_POST;

        $ignoreSsl = $this->systemConfigService->getBool(Configuration::CONFIG_KEY_API_IGNORE_SSL);
        $url       = $this->systemConfigService->getString(Configuration::CONFIG_KEY_DELIVERY_PDF_API_URL);

        if (empty($url)) {
            return $this->responseParser->parseResponse(false, '', OrdersController::DOCUMENT_TYPE_DELIVERY);
        }

        $headerData = [
            'Content-Type: ' . self::API_CONTENT_TYPE . "; charset='utf-8'",
            'Accept: ' . self::API_CONTENT_TYPE,
            'Content-length: ' . strlen($postData),
        ];

        $username = $this->systemConfigService->getString(Configuration::CONFIG_KEY_API_USER_NAME);
        $password = $this->systemConfigService->getString(Configuration::CONFIG_KEY_API_PASSWORD);

        $handle = $this->getCurlHandle($url, $username, $password, $headerData, $method, $postData, $ignoreSsl);

        $response    = curl_exec($handle);
        $errorNumber = curl_errno($handle);
        $statusCode  = curl_getinfo($handle, CURLINFO_HTTP_CODE);

        curl_close($handle);

        if ($errorNumber !== 0 || $statusCode !== 200 || $response === false) {
            $this->logger->error('API error during order PDF read', [
                'method'     => $method,
                'requestUrl' => $url,
                'body'       => $postData,
                'response'   => (string) $response,
                'error'      => $errorNumber,
            ]);

            return $this->responseParser->parseResponse(false, (string) $response, OrdersController::DOCUMENT_TYPE_DELIVERY);
        }

        return $this->responseParser->parseResponse(true, (string) $response, OrdersController::DOCUMENT_TYPE_DELIVERY);
    }

    private function fetchLanguageCode(CustomerEntity $shopwareCustomer): string
    {
        $languageCode = $shopwareCustomer->getLanguage()?->getTranslationCode()?->getCode();

        if ($languageCode === null) {
            $languageCode = $this->systemConfigService->getString(Configuration::CONFIG_KEY_API_FALLBACK_LANGUAGE_CODE);
        }

        return strtoupper(substr($languageCode, 0, 2));
    }
}
