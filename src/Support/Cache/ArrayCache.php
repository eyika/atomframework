<?php

namespace Eyika\Atom\Framework\Support\Cache;

use Eyika\Atom\Framework\Support\Cache\Contracts\CacheInterface;

class ArrayCache implements CacheInterface
{
    /**
     * The array that stores the cached data.
     *
     * @var array
     */
    protected $cache = [];

    protected $serialize;

    public function __construct()
    {
        $this->serialize = config('cache.stores.array.serialize', false);
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $key)
    {
        if (!$this->has($key)) {
            return null;
        }

        $cacheItem = $this->cache[$key];

        // Check if the cache item has expired
        if ($cacheItem['expires_at'] !== 0 && $cacheItem['expires_at'] < time()) {
            $this->delete($key); // Cache expired, delete it
            return null;
        }

        return $this->serialize ? unserialize($cacheItem['value']) : $cacheItem['value'];
    }

    /**
     * {@inheritdoc}
     */
    public function set(string $key, $value, int $ttl = 3600): bool
    {
        $expiresAt = ($ttl === 0) ? 0 : time() + $ttl;

        $this->cache[$key] = [
            'value' => $this->serialize ? serialize($value) : $value,
            'expires_at' => $expiresAt,
        ];

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $key): bool
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
    public function clear(): bool
    {
        $this->cache = [];
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function has(string $key): bool
    {
        if (!isset($this->cache[$key])) {
            return false;
        }

        $cacheItem = $this->cache[$key];

        // Check if the cache has expired
        if ($cacheItem['expires_at'] !== 0 && $cacheItem['expires_at'] < time()) {
            $this->delete($key); // Cache expired, delete it
            return false;
        }

        return true;
    }
}
