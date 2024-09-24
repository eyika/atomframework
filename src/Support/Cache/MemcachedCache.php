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
    public function get(string $key)
    {
        $result = $this->memcached->get($key);

        if ($this->memcached->getResultCode() === \Memcached::RES_NOTFOUND) {
            return null;
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function set(string $key, $value, int $ttl = 3600): bool
    {
        return $this->memcached->set($key, $value, $ttl);
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $key): bool
    {
        return $this->memcached->delete($key);
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
    public function has(string $key): bool
    {
        $this->memcached->get($key);
        return $this->memcached->getResultCode() !== \Memcached::RES_NOTFOUND;
    }
}
