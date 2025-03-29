<?php

namespace Eyika\Atom\Framework\Support\Cache;

use Eyika\Atom\Framework\Exceptions\Cache\InvalidConfigException;
use Eyika\Atom\Framework\Support\Cache\Contracts\CacheInterface;
use Psr\Cache\CacheItemInterface;

class Cache implements CacheInterface
{
    const adapters = [
        'apc' => 'Eyika\Atom\Framework\Support\Cache\ApcCache',
        'array' => 'Eyika\Atom\Framework\Support\Cache\ArrayCache',
        'database' => 'Eyika\Atom\Framework\Support\Cache\DbCache',
        'file' => 'Eyika\Atom\Framework\Support\Cache\FileCache',
        'memcached' => 'Eyika\Atom\Framework\Support\Cache\MemcachedCache',
        'redis' => 'Eyika\Atom\Framework\Support\Cache\RedisCache',
        'dynamodb' => 'Eyika\Atom\Framework\Support\Cache\DynamodbCache'
    ];

    private array $deferred = [];
    protected array $store_config;
    protected CacheInterface $cache_store;

    public function __construct(string|null $store = null)
    {
        $this->setStoreConfig($store);
        $this->initAdapter();
    }

    public function instance(): self
    {
        return $this;
    }

    public function getItem(string $key): CacheItem
    {
        return $this->cache_store->getItem($key);
    }

    public function getItems(array $keys = []): iterable
    {
        $items = [];
        foreach ($keys as $key) {
            $items[$key] = $this->getItem($key);
        }
        return $items;
    }

    public function hasItem(string $key): bool
    {
        return $this->cache_store->hasItem($key);
    }

    public function clear(): bool
    {
        return $this->cache_store->clear();
    }

    public function deleteItem(string $key): bool
    {
        return $this->cache_store->deleteItem($key);
    }

    public function deleteItems(array $keys): bool
    {
        foreach ($keys as $key) {
            $this->deleteItem($key);
        }
        return true;
    }

    /**
     * @param CacheItem $item
     */
    public function save($item): bool
    {
        return $this->cache_store->save($item);
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
        $this->deferred[$item->getKey()] = $item;
        return true;
    }

    public function commit(): bool
    {
        foreach ($this->deferred as $item) {
            $this->save($item);
        }
        $this->deferred = [];
        return true;
    }

    protected function setStoreConfig(string|null $store = null)
    {
        $this->store_config = is_null($store) ? config()->get('cache.stores')[config()->get('cache.default')] : config()->get('cache.stores')[$store];
    }

    protected function initAdapter()
    {
        $classname = self::adapters[$this->store_config['driver']] ?? null;

        if (empty($this->store_config['driver'])) {
            throw new InvalidConfigException('Driver not found or adapter package not installed');
        }

        $this->cache_store = new $classname();
    }
}
