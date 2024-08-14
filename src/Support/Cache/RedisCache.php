<?php

namespace Eyika\Atom\Framework\Support\Cache;

use Eyika\Atom\Framework\Support\Cache\Contracts\CacheInterface;
use Eyika\Atom\Framework\Support\Str;
use Predis\Client;

class RedisCache implements CacheInterface
{
    private $redis;

    public function __construct()
    {
        $this->redis = new Client();  //need to install phredis
        $this->redis->connect('127.0.0.1', 6379);
    }

    public function get(string $key)
    {
        $value = $this->redis->get($key);
        return $value === false ? null : json_decode($value);
    }

    public function set(string $key, $value, int $ttl = 3600): bool
    {
        if (Str::contains($this->redis->setex($key, $ttl, json_encode($value)), ['OK', 'QUEUED'])) {
            return true;
        }
        return false;
    }

    public function delete(string $key): bool
    {
        return $this->redis->del($key) > 0;
    }

    public function clear(): bool
    {
        return $this->redis->flushDB();
    }

    public function has(string $key): bool
    {
        return $this->redis->exists($key);
    }
}
