<?php

declare(strict_types=1);

namespace ReiffIntegrations\Sap\ShopPricing;

use Doctrine\DBAL\Connection;
use ReiffIntegrations\Sap\Struct\Price\ItemCollection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Event\BeforeSendResponseEvent;
use Shopware\Core\PlatformRequest;
use Shopware\Core\SalesChannelRequest;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\Session\Storage\SessionStorageFactoryInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PriceSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly PriceCacheService $cache,
        private readonly RequestStack $requestStack,
        private readonly Connection $connection,
        private readonly DetailedPriceHandler $detailedPriceHandler,
        private readonly SimplePriceHandler $simplePriceHandler,
        private readonly SessionStorageFactoryInterface $sessionFactory
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            BeforeSendResponseEvent::class => 'addCustomerPrices',
        ];
    }

    public function addCustomerPrices(BeforeSendResponseEvent $event): void
    {
        $response = $event->getResponse();

        if ($response instanceof StreamedResponse) {
            return;
        }

        $statusCode = $response->getStatusCode();

        if ($statusCode !== Response::HTTP_OK && $statusCode !== Response::HTTP_NOT_FOUND) {
            return;
        }

        $content = $response->getContent();

        if ($content === false) {
            return;
        }

        if (!str_contains($content, SimplePriceHandler::PRICE_PLACEHOLDER)
            && !str_contains($content, SimplePriceHandler::PRICE_FORMATTED_PLACEHOLDER)
            && !str_contains($content, DetailedPriceHandler::PRICE_SWITCH_PLACEHOLDER_FORMATTED)
            && !str_contains($content, DetailedPriceHandler::PRICE_SWITCH_PLACEHOLDER_FALLBACK)
        ) {
            return;
        }

        $priceData   = $this->getPriceData($event->getRequest());
        $languageId     = (string) $event->getRequest()->headers->get(PlatformRequest::HEADER_LANGUAGE_ID, Defaults::LANGUAGE_SYSTEM);
        $productNumbers = $this->fetchProductNumbers($content);
        $itemCollection = new ItemCollection();

        if ($priceData['debtor_number'] !== null && !empty($productNumbers)) {
            $itemCollection = $this->cache->fetchProductPrices(
                $priceData,
                $productNumbers
            );
        }

        if (str_contains($content, SimplePriceHandler::PRICE_PLACEHOLDER) || str_contains($content, SimplePriceHandler::PRICE_FORMATTED_PLACEHOLDER)) {
            $content = $this->simplePriceHandler->handleSinglePrices(
                $content,
                $languageId,
                $productNumbers,
                $itemCollection,
                $priceData['debtor_number']
            );
        }

        if (str_contains($content, DetailedPriceHandler::PRICE_SWITCH_PLACEHOLDER_FORMATTED) || str_contains($content, DetailedPriceHandler::PRICE_SWITCH_PLACEHOLDER_FALLBACK)) {
            $requestAttributes = $event->getRequest()->attributes;
            /** @var string $salesChannelId */
            $salesChannelId = $requestAttributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID);
            /** @var string $currencyId */
            $currencyId = $requestAttributes->get(SalesChannelRequest::ATTRIBUTE_DOMAIN_CURRENCY_ID);

            $content = $this->detailedPriceHandler->handleSwitchPrices(
                $content,
                $productNumbers,
                $salesChannelId,
                $currencyId,
                $itemCollection,
                $priceData['debtor_number']
            );
        }

        $response->setContent($content);
        $event->setResponse($response);
    }

    private function getPriceData(Request $request): ?array
    {
        $this->restartSession($request);

        $contextToken = $request->getSession()->get('sw-context-token');

        $query = '
            SELECT reiff_customer.debtor_number, reiff_customer.sales_organization, locale.code as language_code
            FROM reiff_customer
            LEFT JOIN sales_channel_api_context context ON context.customer_id = reiff_customer.customer_id
            LEFT JOIN customer ON context.customer_id = customer.customer_id
            LEFT JOIN language ON customer.language_id = language.id
            LEFT JOIN locale ON language.translation_code_id = locale.id
            WHERE context.token = :token
        ';

        /** @var null|array $debtorNumber */
        $data = $this->connection->fetchAllAssociative($query, [
            'token' => $contextToken
        ]);

        if (empty($data)) {
            return null;
        }

        return $data;
    }

    private function fetchProductNumbers(string $content): array
    {
        $singleMatches = [];
        $singlePattern = sprintf(
            '/(%s|%s)(?<productNumber>[^\#]+)\#/',
            preg_quote(SimplePriceHandler::PRICE_PLACEHOLDER, '/'),
            preg_quote(SimplePriceHandler::PRICE_FORMATTED_PLACEHOLDER, '/')
        );
        preg_match_all($singlePattern, $content, $singleMatches, PREG_SET_ORDER);

        $switchMatches = [];
        $switchPattern = sprintf(
            '/(%s|%s)(?<productNumber>[^\#]+)\#/',
            preg_quote(DetailedPriceHandler::PRICE_SWITCH_PLACEHOLDER_FALLBACK, '/'),
            preg_quote(DetailedPriceHandler::PRICE_SWITCH_PLACEHOLDER_FORMATTED, '/')
        );
        preg_match_all($switchPattern, $content, $switchMatches, PREG_SET_ORDER);

        $singleMatchCleaned = array_column($singleMatches, 'productNumber');
        $switchMatchCleaned = array_column($switchMatches, 'productNumber');

        return array_unique(array_merge($singleMatchCleaned, $switchMatchCleaned));
    }

    private function restartSession(Request $request): void
    {
        // Get session from session provider if not provided in session. This happens when the page is fully cached
        $session = $request->hasSession() ? $request->getSession() : $this->createSession($request);
        $request->setSession($session);

        if ($session !== null) {
            // StorefrontSubscriber did not run and set the session name. This can happen when the page is fully cached in the http cache
            if (!$session->isStarted()) {
                $session->setName('session-');
            }

            // The SessionTokenStorage gets the session from the RequestStack. This is at this moment empty as the Symfony request cycle did run already
            $this->requestStack->push($request);
        }
    }

    private function createSession(Request $request): SessionInterface
    {
        $session = new Session($this->sessionFactory->createStorage($request));
        $session->setName('session-');
        $request->setSession($session);

        return $session;
    }
}
