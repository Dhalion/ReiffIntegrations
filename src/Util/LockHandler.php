<?php

declare(strict_types=1);

namespace ReiffIntegrations\Util;

use ReiffIntegrations\Util\Context\ForceState;
use Shopware\Core\Framework\Context;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;
use Symfony\Component\Cache\Exception\CacheException;
use Symfony\Component\Finder\SplFileInfo;

class LockHandler
{
    private const FILE_CACHE_KEY_PREFIX = '#reiff#file#lock';

    private TagAwareAdapterInterface $cache;

    public function __construct(
        TagAwareAdapterInterface $cache
    ) {
        $this->cache = $cache;
    }

    public function createFileLock(SplFileInfo $file): void
    {
        $cacheItemName = $this->getKeyForFile($file);
        $cacheItem     = $this->cache->getItem($cacheItemName);

        $cacheItem->set(true);

        $isSaved = $this->cache->save($cacheItem);

        if (!$isSaved) {
            throw new CacheException('The cache item could not be saved');
        }
    }

    public function hasFileLock(SplFileInfo $file, Context $context): bool
    {
        if ($context->hasState(ForceState::NAME)) {
            return false;
        }

        return $this->hasKeyLock($this->getKeyForFile($file));
    }

    private function hasKeyLock(string $keyName): bool
    {
        return $this->cache->getItem($keyName)->isHit();
    }

    private function getKeyForFile(SplFileInfo $file): string
    {
        $timestamp = $file->getCTime();
        $filename  = $file->getBasename();

        return sprintf(
            '%s#%s%s',
            self::FILE_CACHE_KEY_PREFIX,
            $filename,
            $timestamp
        );
    }
}
