<?php

declare(strict_types=1);

namespace ReiffIntegrations\Api\Client;

use ReiffIntegrations\Util\Configuration;
use Shopware\Core\System\SystemConfig\SystemConfigService;

abstract class AbstractApiClient
{
    public const METHOD_POST = 'POST';
    public const METHOD_GET  = 'GET';

    protected function getCurlHandle(
        string $url,
        string $userName,
        string $password,
        array $headerData,
        string $method,
        string $postData,
        bool $ignoreSsl,
        int $timeout = 10,
    ): \CurlHandle {
        $curl = curl_init($url);

        if (!$curl) {
            throw new \RuntimeException('curl init failed');
        }

        if (!empty($userName) && !empty($password)) {
            curl_setopt($curl, CURLOPT_USERPWD, sprintf('%s:%s', $userName, $password));
            curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        }

        if ($ignoreSsl === true) {
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        }

        curl_setopt($curl, CURLOPT_VERBOSE, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 2);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headerData);
        curl_setopt($curl, CURLOPT_ENCODING, '');
        curl_setopt($curl, CURLOPT_MAXREDIRS, 10);
        curl_setopt($curl, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);

        if ($method === self::METHOD_POST) {
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $postData);
        }

        return $curl;
    }
}
