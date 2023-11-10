<?php

declare(strict_types=1);

namespace ReiffIntegrations\Sap\Offer\ApiClient\Pdf;

use Psr\Log\LoggerInterface;
use ReiffIntegrations\Api\Client\AbstractApiClient;
use ReiffIntegrations\Util\Configuration;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class OfferPdfApiClient extends AbstractApiClient
{
    private const API_CONTENT_TYPE = 'text/xml';

    public function __construct(
        private readonly SystemConfigService $systemConfigService,
        private readonly LoggerInterface $logger,
        private readonly OfferPdfResponseParser $responseParser
    ) {
    }

    public function getOfferPdf(string $documentNumber, Context $context): OfferPdfApiResponse
    {
        $postData = '<soapenv:Envelope
                                xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"
                                xmlns:urn="urn:sap-com:document:sap:rfc:functions">
                        <soapenv:Header/>
                            <soapenv:Body>
                                <urn:ZSHOP_PDF_QUOTATION>
                                  <IV_DOCUMENT_NUMBER>' . $documentNumber . '</IV_DOCUMENT_NUMBER>
                                </urn:ZSHOP_PDF_QUOTATION>
                            </soapenv:Body>
                        </soapenv:Envelope>';
        $method = self::METHOD_POST;

        $ignoreSsl = $this->systemConfigService->getBool(Configuration::CONFIG_KEY_API_IGNORE_SSL);
        $url       = $this->systemConfigService->getString(Configuration::CONFIG_KEY_OFFER_PDF_API_URL);

        if (empty($url)) {
            return $this->responseParser->parseResponse(false, '');
        }

        $username = $this->systemConfigService->getString(Configuration::CONFIG_KEY_API_USER_NAME);
        $password = $this->systemConfigService->getString(Configuration::CONFIG_KEY_API_PASSWORD);

        $headerData = [
            'Content-Type: ' . self::API_CONTENT_TYPE,
            'Accept: ' . self::API_CONTENT_TYPE,
            'Content-length: ' . strlen($postData),
        ];

        $handle = $this->getCurlHandle($url, $username, $password, $headerData, $method, $postData, $ignoreSsl);

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
        $this->logger->error('API error during order PDF read', [
            'method'     => $method,
            'requestUrl' => $exportUrl,
            'body'       => $serializedData,
            'response'   => $response,
            'error'      => $errorNumber,
        ]);
    }
}
