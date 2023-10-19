<?php

declare(strict_types=1);

namespace ReiffIntegrations\Sap\ShopPricing;

use ReiffIntegrations\Sap\Exception\TimeoutException;
use ReiffIntegrations\Sap\ShopPricing\ApiClient\PriceApiClient;
use ReiffIntegrations\Sap\Struct\Price\ItemCollection;
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
        private readonly PriceApiClient $client
    ) {
    }

    public function fetchProductPrices(string $debtorNumber, array $productNumbers): ItemCollection
    {
        $missingProductNumbers = [];
        $prices                = $this->getCachedPrices($debtorNumber, $productNumbers);

        foreach ($productNumbers as $productNumber) {
            $cachedPrice = $prices->getItemsByNumber($productNumber);

            if ($cachedPrice->count() === 0) {
                $missingProductNumbers[] = $productNumber;
            }
        }

        if (!empty($missingProductNumbers)) {
            $uncachedPrices = $this->getUncachedPrices($debtorNumber, $missingProductNumbers);

            if ($uncachedPrices->count() === 0) {
                return $prices;
            }

            $this->updatePrices($debtorNumber, $uncachedPrices, $prices);
        }

        return $prices;
    }

    private function getCachedPrices(string $debtorNumber, array $productNumbers): ItemCollection
    {
        $result      = new ItemCollection();
        $cacheResult = $this->cache->getItems($this->getCacheKeys($debtorNumber, $productNumbers));

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

    private function getUncachedPrices(string $debtorNumber, array $productNumbers): ItemCollection
    {
        $circuitBreaker = $this->cache->getItem(self::CACHE_CIRCUIT_BREAKER_TAG);

        if (!$circuitBreaker->isHit()) {
            try {
                return $this->client->getPrices($productNumbers, $debtorNumber);
            } catch (TimeoutException $exception) {
                $circuitBreaker->set(true);
                $circuitBreaker->expiresAfter(self::CIRCUIT_BREAKER_EXPIRATION_IN_SECONDS);

                $this->cache->save($circuitBreaker);
            } catch (\Throwable $throwable) {
                // exception is not logged due to created cache items in getCachedPrices
                throw $throwable;
            }
        }

        return new ItemCollection();
    }

    private function updatePrices(
        string $debtorNumber,
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
            $cacheKey = $this->getCacheKey($debtorNumber, (string) $productNumber);

            $cacheItem = $this->cache->getItem($cacheKey);
            $cacheItem->set($priceItemCollection);
            $cacheItem->expiresAfter(self::PRICE_CACHE_EXPIRATION_IN_SECONDS);
            $cacheItem->tag([$cacheKey]);

            $this->cache->saveDeferred($cacheItem);
        }

        $this->cache->commit();
    }

    private function getCacheKeys(string $debtorNumber, array $productNumbers): array
    {
        $keys = [];

        foreach ($productNumbers as $productNumber) {
            $keys[] = $this->getCacheKey($debtorNumber, $productNumber);
        }

        return $keys;
    }

    private function getCacheKey(string $debtorNumber, string $productNumber): string
    {
        $productNumber = str_replace(str_split(ItemInterface::RESERVED_CHARACTERS), '', $productNumber);

        return sprintf('%s#%s#%s', self::CACHE_CUSTOMER_PRICE_TAG, $debtorNumber, $productNumber);
    }
}
