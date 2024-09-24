<?php

namespace Eyika\Atom\Framework\Support\Cache;

use Eyika\Atom\Framework\Exceptions\Cache\InvalidConfigException;
use Eyika\Atom\Framework\Support\Cache\Contracts\CacheInterface;
use Eyika\Atom\Framework\Support\Database\mysqly;

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

    protected array $store_config;
    protected CacheInterface $cache_store;

    public function __construct(string $store = null)
    {
        $this->setStoreConfig($store);

        $this->initAdapter();
    }


    public function setCacheStore(CacheInterface $cache)
    {
        $this->cache_store = $cache;
    }

    public function getFileSystemAdapter()
    {
        return $this->cache_store;
    }

    public function setStore(string $store = null)
    {
        $this->setStoreConfig($store);
        $this->initAdapter();
    }

    protected function setStoreConfig(string $store = null)
    {
        $this->store_config = is_null($store) ? config('cache.stores')[config('cache.default')] : config('cache.stores')[$store];
    }

    protected function initAdapter()
    {
        $classname = self::adapters[$this->store_config['driver']] ?? null;

        if (empty($this->store_config['driver'])) {
            throw new InvalidConfigException('driver not found or adapter package not installed');
        }

        $this->cache_store = new $classname();
    }

    public function get(string $key)
    {
        $value = mysqly::cache($key);
        return $value === false ? null : $value;
    }

    public function set(string $key, $value, int $ttl = 3600): bool
    {
        return mysqly::cache($key, $value, $ttl) ? true : false;
    }

    public function delete(string $key): bool
    {
        return mysqly::uncache($key);
    }

    public function clear(): bool
    {
        return mysqly::clear_cache();
    }

    public function has(string $key): bool
    {
        return mysqly::cache($key) ? true : false;
    }
}
