<?php

declare(strict_types=1);

namespace ReiffIntegrations\Seeburger\Client;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;

class SeeburgerClient
{
    public function post(string $idoc, string $url): string
    {
        $client = new Client([
            'base_url'             => $url,
            RequestOptions::VERIFY => sprintf('%s/ca.pem', __DIR__),
        ]);

        $response = $client->post($url, [
            RequestOptions::HEADERS => [
                'Content-Type' => 'text/plain; charset=utf-8',
            ],
            RequestOptions::BODY             => trim($idoc),
            RequestOptions::FORCE_IP_RESOLVE => 'v4',
        ]);

        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException('No successful response code');
        }

        return $response->getBody()->getContents();
    }
}
