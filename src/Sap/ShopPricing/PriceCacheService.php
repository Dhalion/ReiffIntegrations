<?php

declare(strict_types=1);

namespace ReiffIntegrations\Sap\ShopPricing;

use ReiffIntegrations\Sap\Exception\TimeoutException;
use ReiffIntegrations\Sap\ShopPricing\ApiClient\PriceApiClient;
use ReiffIntegrations\Sap\Struct\Price\ItemCollection;
use ReiffIntegrations\Util\Configuration;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;
use Symfony\Contracts\Cache\ItemInterface;

class PriceCacheService
{
    private const CACHE_CIRCUIT_BREAKER_TAG             = '#reiff#sap#price#disabled';
    private const CACHE_CUSTOMER_PRICE_TAG              = '#reiff#product#price';
    private const CIRCUIT_BREAKER_EXPIRATION_IN_SECONDS = 240;
    private const PRICE_CACHE_EXPIRATION_IN_SECONDS     = 60 * 15;

    public function __construct(
        private readonly TagAwareAdapterInterface $cache,
        private readonly PriceApiClient $client,
        private readonly SystemConfigService $systemConfigService,
    ) {
    }

    public function fetchProductPrices(array $priceData, array $productNumbers): ItemCollection
    {
        $missingProductNumbers = [];
        $prices                = $this->getCachedPrices($priceData, $productNumbers);

        foreach ($productNumbers as $productNumber) {
            $cachedPrice = $prices->getItemsByNumber($productNumber);

            if ($cachedPrice->count() === 0) {
                $missingProductNumbers[] = $productNumber;
            }
        }

        if (!empty($missingProductNumbers)) {
            $uncachedPrices = $this->getUncachedPrices($priceData, $missingProductNumbers);

            if ($uncachedPrices->count() === 0) {
                return $prices;
            }

            $this->updatePrices($priceData, $uncachedPrices, $prices);
        }

        return $prices;
    }

    private function getCachedPrices(array $priceData, array $productNumbers): ItemCollection
    {
        $result      = new ItemCollection();
        $cacheResult = $this->cache->getItems($this->getCacheKeys($priceData, $productNumbers));

        foreach ($cacheResult as $cachedPrice) {
            if ($cachedPrice->isHit() && $cachedPrice->get()) {
                /** @var ItemCollection $priceCollection */
                $priceCollection = $cachedPrice->get();

                foreach ($priceCollection->getElements() as $price) {
                    $result->set(sprintf(ItemCollection::ITEM_KEY_HANDLE, $price->getProductNumber(), $price->getQuantity()), $price);
                }
            }
        }

        return $result;
    }

    private function getUncachedPrices(
        array $priceData,
        array $productNumbers
    ): ItemCollection {
        $circuitBreaker = $this->cache->getItem(self::CACHE_CIRCUIT_BREAKER_TAG);

        if (!$circuitBreaker->isHit()) {
            try {
                $debtorNumber      = $this->fetchDebtorNumber($priceData);
                $salesOrganisation = $this->fetchSalesOrganisation($priceData);
                $languageCode      = $this->fetchLanguageCode($priceData);

                return $this->client->getPrices(
                    $debtorNumber,
                    $salesOrganisation,
                    $languageCode,
                    $productNumbers,
                );
            } catch (TimeoutException $exception) {
                $circuitBreaker->set(true);
                $circuitBreaker->expiresAfter(self::CIRCUIT_BREAKER_EXPIRATION_IN_SECONDS);

                $this->cache->save($circuitBreaker);
            }
        }

        return new ItemCollection();
    }

    private function updatePrices(
        array $priceData,
        ItemCollection $uncachedPrices,
        ItemCollection $prices
    ): void {
        $pricesToSave = [];

        foreach ($uncachedPrices as $price) {
            if (!array_key_exists($price->getProductNumber(), $pricesToSave)) {
                $pricesToSave[$price->getProductNumber()] = new ItemCollection();
            }

            $pricesToSave[$price->getProductNumber()]->set(sprintf(ItemCollection::ITEM_KEY_HANDLE, $price->getProductNumber(), $price->getQuantity()), $price);
            $prices->set(sprintf(ItemCollection::ITEM_KEY_HANDLE, $price->getProductNumber(), $price->getQuantity()), $price);
        }

        foreach ($pricesToSave as $productNumber => $priceItemCollection) {
            /** $productNumber is converted to int due looking int-ish */
            $cacheKey = $this->getCacheKey($priceData, (string) $productNumber);

            $cacheItem = $this->cache->getItem($cacheKey);
            $cacheItem->set($priceItemCollection);
            $cacheItem->expiresAfter(self::PRICE_CACHE_EXPIRATION_IN_SECONDS);
            $cacheItem->tag([$cacheKey]);

            $this->cache->saveDeferred($cacheItem);
        }

        $this->cache->commit();
    }

    private function getCacheKeys(array $priceData, array $productNumbers): array
    {
        $keys = [];

        foreach ($productNumbers as $productNumber) {
            $keys[] = $this->getCacheKey($priceData, $productNumber);
        }

        return $keys;
    }

    private function getCacheKey(array $priceData, string $productNumber): string
    {
        $salesOrganisation = $this->fetchSalesOrganisation($priceData);
        $debtorNumber      = $this->fetchDebtorNumber($priceData);

        $productNumber = str_replace(str_split(ItemInterface::RESERVED_CHARACTERS), '', $productNumber);

        return sprintf(
            '%s#%s#%s#%s',
            self::CACHE_CUSTOMER_PRICE_TAG,
            $debtorNumber,
            $productNumber,
            $salesOrganisation
        );
    }

    private function fetchSalesOrganisation(array $priceData): string
    {
        $salesOrganisation = $priceData['sales_organisation'] ?? null;

        if (empty($salesOrganisation) || $salesOrganisation === '-') {
            $salesOrganisation = $this->systemConfigService->getString(
                Configuration::CONFIG_KEY_API_FALLBACK_SALES_ORGANISATION
            );
        }

        return $salesOrganisation;
    }

    private function fetchLanguageCode(array $priceData): string
    {
        $languageCode = $priceData['language_code'] ?? null;

        if ($languageCode === null) {
            $languageCode = $this->systemConfigService->getString(
                Configuration::CONFIG_KEY_API_FALLBACK_LANGUAGE_CODE
            );
        }

        return strtoupper(substr($languageCode, 0, 2));
    }

    private function fetchDebtorNumber(array $priceData): string
    {
        $debtorNumber = $priceData['debtor_number'] ?? null;

        if (empty($debtorNumber) || $debtorNumber === '-') {
            $debtorNumber = $this->systemConfigService->getString(
                Configuration::CONFIG_KEY_API_FALLBACK_DEBTOR_NUMBER
            );
        }

        return $debtorNumber;
    }
}
