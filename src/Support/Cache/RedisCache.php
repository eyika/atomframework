<?php

namespace Eyika\Atom\Framework\Support\Cache;

use Eyika\Atom\Framework\Support\Cache\Contracts\CacheInterface;
use Eyika\Atom\Framework\Support\Str;
use Predis\Client;

class RedisCache implements CacheInterface
{
    private $redis;
    protected array $deferredItems = [];

    public function __construct()
    {
        $config = config()->get('cache.stores.redis');

        $this->redis = new Client();  //need to install phredis
        $this->redis->connect('127.0.0.1', 6379);
    }

    /**
     * {@inheritdoc}
     */
    public function getItem(string $key): CacheItem
    {
        $value = $this->redis->get($key);
        return $value === false ? new CacheItem($key, null, false) : new CacheItem($key, json_decode($value), true);
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
     * {@inheritdoc}
     */
    public function hasItem(string $key): bool
    {
        return $this->redis->exists($key);
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): bool
    {
        return $this->redis->flushDB();
    }

    /**
     * {@inheritdoc}
     */
    public function deleteItem(string $key): bool
    {
        return $this->redis->del($key) > 0;
    }

    /**
     * @throws InvalidArgumentException
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
        if (Str::contains($this->redis->setex($item->getKey(), $item->getExpiration(), json_encode($item->get())), ['OK', 'QUEUED'])) {
            return true;
        }
        return false;
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
