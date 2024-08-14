<?php

namespace Eyika\Atom\Support\Cache;

use Eyika\Atom\Support\Cache\Contracts\CacheInterface;
use Eyika\Atom\Support\Database\mysqly;

class DbCache implements CacheInterface
{
    public function __construct()
    {
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
