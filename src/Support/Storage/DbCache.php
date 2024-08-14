<?php

namespace Basttyy\FxDataServer\libs\Storage;

use Basttyy\FxDataServer\libs\Interfaces\CacheInterface;
use Basttyy\FxDataServer\libs\mysqly;

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
