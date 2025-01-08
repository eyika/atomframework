<?php

namespace Eyika\Atom\Framework\Support\Cache;

use Eyika\Atom\Framework\Exceptions\Cache\IncompleteInstallationException;
use Eyika\Atom\Framework\Support\Cache\Contracts\CacheInterface;

class ApcCache implements CacheInterface
{
    protected array $deferredItems = [];

    public function __construct()
    {
        if (!function_exists('apcu_fetch')) {
            throw new IncompleteInstallationException('apcu extension is not installed or is disabled');
        }
    }
    /**
     * {@inheritdoc}
     */
    public function getItem(string $key): CacheItem
    {
        $success = false;
        $value = apcu_fetch($key, $success);

        return new CacheItem($key, $value, $success);
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
        return apcu_exists($key);
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
    public function deleteItem(string $key): bool
    {
        return apcu_delete($key);
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
        return apcu_store($item->getKey(), $item->get(), $item->getExpiration());
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
