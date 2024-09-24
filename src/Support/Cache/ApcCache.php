<?php

namespace Eyika\Atom\Framework\Support\Cache;

use Eyika\Atom\Framework\Exceptions\Cache\IncompleteInstallationException;
use Eyika\Atom\Framework\Support\Cache\Contracts\CacheInterface;

class ApcCache implements CacheInterface
{
    public function __construct()
    {
        if (!function_exists('apcu_fetch')) {
            throw new IncompleteInstallationException('apcu extension is not installed or is disabled');
        }
    }
    /**
     * {@inheritdoc}
     */
    public function get(string $key)
    {
        $success = false;
        $value = apcu_fetch($key, $success);
        return $success ? $value : null;
    }

    /**
     * {@inheritdoc}
     */
    public function set(string $key, $value, int $ttl = 3600): bool
    {
        return apcu_store($key, $value, $ttl);
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $key): bool
    {
        return apcu_delete($key);
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): bool
    {
        return apcu_clear_cache();
    }

    /**
     * {@inheritdoc}
     */
    public function has(string $key): bool
    {
        return apcu_exists($key);
    }
}
