<?php

namespace Eyika\Atom\Framework\Support\Session;

use Redis;
use SessionHandlerInterface;
use SessionIdInterface;
use SessionUpdateTimestampHandlerInterface;

class RedisSessionHandler implements SessionHandlerInterface, SessionIdInterface, SessionUpdateTimestampHandlerInterface
{
    private Redis $redis;
    private string $prefix;
    private int $ttl;

    public function __construct()
    {
        $this->redis = new Redis();
        $this->prefix = config('session.redis_prefix', 'sess:');
        $this->ttl = config('session.ttl', 1440); // Default to 24 minutes if not set

        $redisHost = env('REDIS_HOST', '127.0.0.1');
        $redisPort = env('REDIS_PORT', 6379);
        $redisPassword = env('REDIS_PASSWORD', null);

        $this->redis->connect($redisHost, $redisPort);
        if ($redisPassword) {
            $this->redis->auth($redisPassword);
        }
    }

    public function open($savePath, $sessionName): bool
    {
        return true; // Redis connection is already established in constructor
    }

    public function close(): bool
    {
        $this->redis->close();
        return true;
    }

    public function destroy($sessionId): bool
    {
        return $this->redis->del($this->prefix . $sessionId) > 0;
    }

    public function gc($maxLifetime): int|false
    {
        // Redis handles expiration automatically via TTL; no explicit GC needed.
        return true;
    }

    public function read($sessionId): string
    {
        $data = $this->redis->get($this->prefix . $sessionId);
        return $data ?: '';
    }

    public function write($sessionId, $sessionData): bool
    {
        return $this->redis->setex($this->prefix . $sessionId, $this->ttl, $sessionData);
    }

    public function create_sid(): string
    {
        return bin2hex(random_bytes(16));
    }

    public function validateId($sessionId): bool
    {
        return $this->redis->exists($this->prefix . $sessionId) > 0;
    }

    public function updateTimestamp($sessionId, $sessionData): bool
    {
        return $this->redis->expire($this->prefix . $sessionId, $this->ttl);
    }
}
