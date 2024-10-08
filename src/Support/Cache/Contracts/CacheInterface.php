<?php

namespace Eyika\Atom\Framework\Support\Cache\Contracts;

interface CacheInterface
{
    public function get(string $key);
    public function set(string $key, $value, int $ttl = 3600): bool;
    public function delete(string $key): bool;
    public function clear(): bool;
    public function has(string $key): bool;
}
