<?php

namespace Basttyy\FxDataServer\libs\Storage;

use Basttyy\FxDataServer\libs\Interfaces\CacheInterface;

class RedisCache implements CacheInterface
{
    private $redis;

    public function __construct()
    {
        $this->redis = new Redis();  //need to install phredis
        $this->redis->connect('127.0.0.1', 6379);
    }

    public function get(string $key)
    {
        $value = $this->redis->get($key);
        return $value === false ? null : json_decode($value);
    }

    public function set(string $key, $value, int $ttl = 3600): bool
    {
        return $this->redis->setex($key, $ttl, json_encode($value));
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
