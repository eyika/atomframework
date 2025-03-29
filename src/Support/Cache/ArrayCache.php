<?php

namespace Eyika\Atom\Framework\Support\Cache;

use Eyika\Atom\Framework\Support\Cache\Contracts\CacheInterface;
use Psr\Cache\CacheItemInterface;

class ArrayCache implements CacheInterface
{
    /**
     * The array that stores the cached data.
     *
     * @var CacheItem[]
     */
    protected $cache = [];
    protected array $deferredItems = [];

    protected $serialize;

    public function __construct()
    {
        $this->serialize = config()->get('cache.stores.array.serialize', false);
    }

    /**
     * {@inheritdoc}
     */
    public function getItem($key): CacheItemInterface
    {
        if (!$this->hasItem($key)) {
            $value = null;
        }

        $cacheItem = $this->cache[$key];

        // Check if the cache item has expired
        if ($cacheItem['expires_at'] !== 0 && $cacheItem['expires_at'] < time()) {
            $this->deleteItem($key); // Cache expired, delete it
            $value = null;
        }
        $value = $this->serialize ? unserialize($cacheItem['value']) : $cacheItem['value'];

        return new CacheItem($key, $value, $value !== null);
    }

    /**
     * @param string[] $keys
     * 
     * @return CacheItemInterface[]
     * 
     * @throws InvalidArgumentException
     */
    public function getItems($keys = []): iterable
    {
        $items = [];
        foreach ($keys as $key) {
            $items[$key] = $this->getItem($key);
        }
        return $items;
    }

    /**
     * @throws InvalidArgumentException
     */
    public function hasItem(string $key): bool
    {
        if (!isset($this->cache[$key])) {
            return false;
        }

        $cacheItem = $this->cache[$key];

        // Check if the cache has expired
        if ($cacheItem->getExpiration() !== 0 && $cacheItem->getExpiration() < time()) {
            $this->deleteItem($key); // Cache expired, delete it
            return false;
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): bool
    {
        $this->cache = [];
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteItem(string $key): bool
    {
        if (isset($this->cache[$key])) {
            unset($this->cache[$key]);
            return true;
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteItems($keys = []): bool
    {
        foreach ($keys as $key) {
            if (!$this->deleteItem($key))
                return false;
        }
        return true;
    }

    /**
     * @param CacheItem $item
     */
    public function save($item): bool
    {
        $expiresAt = ($item->getExpiration() === 0) ? 0 : time() + $item->getExpiration();

        $this->cache[$item->getKey()] = [
            'value' => $this->serialize ? serialize($item->get()) : $item->get(),
            'expires_at' => $expiresAt,
        ];

        return true;
    }

    /**
     * @param CacheItem $item
     */
    public function setItem($item): bool
    {
        return $this->save($item);
    }

    /**
     * @param CacheItem $item
     */
    public function saveDeferred($item): bool
    {
        if (!$item instanceof CacheItem) {
            return false;
        }

        $this->deferredItems[$item->getKey()] = $item;
        return true;
    }

    public function commit(): bool
    {
        $allSaved = true;

        foreach ($this->deferredItems as $key => $item) {
            if (!$this->save($item)) {
                $allSaved = false;
            }
        }

        // Clear deferred items after attempting to save
        $this->deferredItems = [];

        return $allSaved;
    }
}
