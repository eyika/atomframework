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
        $this->serialize = config('cache.stores.array.serialize', false);
    }

    /**
     * {@inheritdoc}
     */
    public function getItem($key): CacheItemInterface
    {
        // A miss must return an empty item, not read a key that isn't there. Previously the
        // `$value = null` assignments were dead — the line below overwrote them unconditionally —
        // so a miss fell straight through to `$this->cache[$key]` on an absent key, and an
        // expired entry was deleted and then read anyway.
        if (!$this->hasItem($key)) {
            return new CacheItem($key, null, false);
        }

        $cacheItem = $this->cache[$key];
        $value = $this->serialize ? unserialize($cacheItem['value']) : $cacheItem['value'];

        return new CacheItem($key, $value, true);
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

        // `save()` stores a plain array (`['value', 'expires_at']`), so this read it as an object
        // and fataled with "Call to a member function getExpiration() on array" for every hit —
        // the array driver could not report a stored key at all. `getItem()` directly below
        // already read `expires_at` correctly, which is why the two disagreed.
        if ($cacheItem['expires_at'] !== 0 && $cacheItem['expires_at'] < time()) {
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
