<?php

declare(strict_types=1);

namespace ReiffIntegrations\Sap\DeliveryInformation;

use ReiffIntegrations\Sap\DeliveryInformation\ApiClient\AvailabilityApiClient;
use ReiffIntegrations\Sap\DeliveryInformation\Struct\AvailabilityStruct;
use ReiffIntegrations\Sap\DeliveryInformation\Struct\AvailabilityStructCollection;
use ReiffIntegrations\Sap\Exception\TimeoutException;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;
use Symfony\Contracts\Cache\ItemInterface;

class AvailabilityService
{
    private const CACHE_CIRCUIT_BREAKER_TAG                = '#reiff#sap#availability#disabled';
    private const CACHE_AVAILABILITY_TAG                   = '#reiff#product#availability';
    private const CIRCUIT_BREAKER_EXPIRATION_IN_SECONDS    = 180;
    private const AVAILABILITY_CACHE_EXPIRATION_IN_SECONDS = 60 * 10;

    public function __construct(
        private readonly TagAwareAdapterInterface $cache,
        private readonly AvailabilityApiClient $apiClient
    ) {
    }

    public function fetchAvailabilities(array $productNumbers): AvailabilityStructCollection
    {
        $availabilities        = $this->getCachedAvailabilities($productNumbers);
        $missingProductNumbers = [];

        foreach ($productNumbers as $productNumber) {
            $cachedData = $availabilities->getAvailabilityByNumber($productNumber);

            if ($cachedData === null) {
                $missingProductNumbers[] = $productNumber;
            }
        }

        if (!empty($missingProductNumbers)) {
            $uncachedAvailabilities = $this->getUncachedAvailabilities($missingProductNumbers);

            if ($uncachedAvailabilities->count() === 0) {
                return $availabilities;
            }

            $this->updateAvailabilities($uncachedAvailabilities, $availabilities);
        }

        return $availabilities;
    }

    private function getCachedAvailabilities(array $productNumbers): AvailabilityStructCollection
    {
        $result      = new AvailabilityStructCollection();
        $cacheResult = $this->cache->getItems($this->getCacheKeys($productNumbers));

        foreach ($cacheResult as $cachedResult) {
            if ($cachedResult->isHit() && $cachedResult->get()) {
                /** @var AvailabilityStruct $availabilityItem */
                $availabilityItem = $cachedResult->get();

                $result->set($availabilityItem->getProductNumber(), $availabilityItem);
            }
        }

        return $result;
    }

    private function getUncachedAvailabilities(array $productNumbers): AvailabilityStructCollection
    {
        $uncachedAvailabilities = new AvailabilityStructCollection();
        $circuitBreaker         = $this->cache->getItem(self::CACHE_CIRCUIT_BREAKER_TAG);

        if (!$circuitBreaker->isHit()) {
            try {
                $uncachedAvailabilities = $this->apiClient->getAvailability($productNumbers);
            } catch (TimeoutException $exception) {
                $circuitBreaker->set(true);
                $circuitBreaker->expiresAfter(self::CIRCUIT_BREAKER_EXPIRATION_IN_SECONDS);

                $this->cache->save($circuitBreaker);
            } catch (\Throwable $throwable) {
                throw $throwable;
                // exception is not logged due to created cache items in getCachedAvailabilities
            }
        }

        return $uncachedAvailabilities;
    }

    private function updateAvailabilities(
        AvailabilityStructCollection $uncachedAvailabilities,
        AvailabilityStructCollection $cachedAvailabilities
    ): void {
        foreach ($uncachedAvailabilities->getElements() as $availability) {
            $cachedAvailabilities->set($availability->getProductNumber(), $availability);

            $cacheKey = $this->getCacheKey($availability->getProductNumber());

            $cacheItem = $this->cache->getItem($cacheKey);
            $cacheItem->set($availability);
            $cacheItem->expiresAfter(self::AVAILABILITY_CACHE_EXPIRATION_IN_SECONDS);
            $cacheItem->tag([$cacheKey]);

            $this->cache->saveDeferred($cacheItem);
        }

        $this->cache->commit();
    }

    private function getCacheKeys(array $productNumbers): array
    {
        $keys = [];

        foreach ($productNumbers as $productNumber) {
            $keys[] = $this->getCacheKey((string) $productNumber);
        }

        return $keys;
    }

    private function getCacheKey(string $productNumber): string
    {
        $productNumber = str_replace(str_split(ItemInterface::RESERVED_CHARACTERS), '', (string) $productNumber);

        return sprintf('%s#%s', self::CACHE_AVAILABILITY_TAG, $productNumber);
    }
}
