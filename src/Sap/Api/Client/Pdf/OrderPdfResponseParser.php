<?php

declare(strict_types=1);

namespace ReiffIntegrations\Sap\Api\Client\Pdf;

use Psr\Log\LoggerInterface;
use ReiffIntegrations\Sap\Struct\PdfStruct;

class OrderPdfResponseParser
{
    private const PDF_ELEMENT = 'ES_DOCUMENT';

    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    public function parseResponse(bool $success, string $rawResponse, string $documentType): OrderPdfApiResponse
    {
        $document = new PdfStruct();

        if (empty($rawResponse) || !$success) {
            return new OrderPdfApiResponse(false, $rawResponse, $document);
        }

        $documentData = [];

        try {
            $xmlReturn = (new \SimpleXMLElement($rawResponse))->xpath(sprintf('//%s', self::PDF_ELEMENT));

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

            return new OrderPdfApiResponse(false, $rawResponse, $document);
        }

        if (count($documentData) === 0) {
            $this->logger->error('Document data is empty', [
                'response' => $rawResponse,
            ]);

            return new OrderPdfApiResponse(false, $rawResponse, $document);
        }

        try {
            $document = $this->buildDocument($documentData);
        } catch (\Exception $exception) {
            $this->logger->error('Error determining the order PDF', [
                'response'     => $rawResponse,
                'error'        => $exception->getMessage(),
                'documentData' => $documentData,
            ]);

            return new OrderPdfApiResponse(false, $rawResponse, $document);
        }

        return new OrderPdfApiResponse($success, $rawResponse, $document);
    }

    private function buildDocument(array $documentData): PdfStruct
    {
        if (!isset($documentData['HEADER']) || !is_array($documentData['HEADER']) || count($documentData['HEADER']) === 0) {
            throw new \RuntimeException('Document header information missing');
        }

        return new PdfStruct(
            (string) $documentData['HEADER']['DOCUMENT_NUMBER'],
            (string) $documentData['HEADER']['CUSTOMER'],
            (string) $documentData['ATTACHEMENT']['FILE_NAME'],
            (string) base64_decode($documentData['ATTACHEMENT']['FILE'])
        );
    }
}
