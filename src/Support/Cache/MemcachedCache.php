<?php

namespace Eyika\Atom\Framework\Support\Cache;

use Eyika\Atom\Framework\Support\Cache\Contracts\CacheInterface;

class MemcachedCache implements CacheInterface
{
    /**
     * The Memcached instance.
     *
     * @var \Memcached
     */
    protected $memcached;
    protected array $deferredItems = [];

    /**
     * Constructor to initialize the Memcached connection.
     *
     * @param array $servers
     */
    public function __construct()
    {
        $config = config('cache.stores.memcached');

        $servers = $config['servers'];

        $this->memcached = new \Memcached();

        // Add the servers to Memcached
        foreach ($servers as $server) {
            $host = $server['host'];
            $port = $server['port'];
            $weight = $server['weight'];
            $this->memcached->addServer($host, $port);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getItem(string $key): CacheItem
    {
        $result = $this->memcached->get($key);

        if ($this->memcached->getResultCode() === \Memcached::RES_NOTFOUND) {
            return new CacheItem($key, $result, false);
        }

        return new CacheItem($key, $result, true);
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
        $this->memcached->get($key);
        return $this->memcached->getResultCode() !== \Memcached::RES_NOTFOUND;
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): bool
    {
        return $this->memcached->flush();
    }

    /**
     * {@inheritdoc}
     */
    public function deleteItem(string $key): bool
    {
        return $this->memcached->delete($key);
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
        return $this->memcached->set($item->getKey(), $item->get(), $item->getExpiration());
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
