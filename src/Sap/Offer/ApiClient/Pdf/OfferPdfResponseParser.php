<?php

declare(strict_types=1);

namespace ReiffIntegrations\Sap\Offer\ApiClient\Pdf;

use Psr\Log\LoggerInterface;
use ReiffIntegrations\Sap\Struct\PdfStruct;
use ReiffIntegrations\Sap\Util\AbstractResponseParser;

class OfferPdfResponseParser extends AbstractResponseParser
{
    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    public function parseResponse(bool $success, string $rawResponse): OfferPdfApiResponse
    {
        $document = new PdfStruct();

        if (empty($rawResponse) || !$success) {
            return new OfferPdfApiResponse(false, $rawResponse, $document);
        }

        $documentData = [];

        try {
            $xmlReturn = (new \SimpleXMLElement($rawResponse))->xpath('//ES_DOCUMENT');

            if (is_array($xmlReturn)) {
                $xmlReturn = reset($xmlReturn);

                if ($xmlReturn) {
                    $documentData = (array) json_decode((string) json_encode((array) $xmlReturn), true);
                }
            }
        } catch (\Exception $exception) {
            $this->logger->error('The XML file cannot be generated', [
                'response' => $rawResponse,
                'error'    => $exception->getMessage(),
            ]);

            return new OfferPdfApiResponse(false, $rawResponse, $document);
        }

        if (count($documentData) === 0) {
            $this->logger->error('Document data is empty', [
                'response' => $rawResponse,
            ]);

            return new OfferPdfApiResponse(false, $rawResponse, $document);
        }

        try {
            $document = $this->buildDocument($documentData);
        } catch (\Exception $exception) {
            $this->logger->error('Error determining the order PDF', [
                'response'     => $rawResponse,
                'error'        => $exception->getMessage(),
                'documentData' => $documentData,
            ]);

            return new OfferPdfApiResponse(false, $rawResponse, $document);
        }

        return new OfferPdfApiResponse($success, $rawResponse, $document);
    }

    private function buildDocument(array $documentData): PdfStruct
    {
        if (!isset($documentData['HEADER']) || !is_array($documentData['HEADER']) || count($documentData['HEADER']) === 0 || !is_string($documentData['HEADER']['DOCUMENT_NUMBER'])) {
            throw new \RuntimeException('Document header information missing');
        }

        if (!isset($documentData['ATTACHMENT']) || !is_array($documentData['ATTACHMENT']) || count($documentData['ATTACHMENT']) === 0 || !is_string($documentData['ATTACHMENT']['FILE_NAME'])) {
            throw new \RuntimeException('Document attachment information missing');
        }

        return new PdfStruct(
            $this->getData($documentData['HEADER'], 'DOCUMENT_NUMBER'),
            $this->getData($documentData['HEADER'], 'CUSTOMER'),
            $this->getData($documentData['ATTACHMENT'], 'FILE_NAME'),
            /** @phpstan-ignore-next-line */
            (string) base64_decode($this->getData($documentData['ATTACHMENT'], 'FILE'))
        );
    }
}
