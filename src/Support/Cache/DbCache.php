<?php

namespace Eyika\Atom\Framework\Support\Cache;

use Eyika\Atom\Framework\Support\Cache\Contracts\CacheInterface;
use Eyika\Atom\Framework\Support\Database\mysqly;

class DbCache implements CacheInterface
{
    protected string $table;

    public function __construct()
    {
        $config = config('cache.stores.database');

        $this->table = $config['table'] ?? '_cache';
    }

    public function get(string $key)
    {
        $value = mysqly::cache($key, table: $this->table);
        return $value === false ? null : $value;
    }

    public function set(string $key, $value, int $ttl = 3600): bool
    {
        return mysqly::cache($key, $value, $ttl, $this->table) ? true : false;
    }

    public function delete(string $key): bool
    {
        return mysqly::uncache($key, $this->table);
    }

    public function clear(): bool
    {
        return mysqly::clear_cache($this->table);
    }

    public function has(string $key): bool
    {
        return mysqly::cache($key, table: $this->table) ? true : false;
    }
}
