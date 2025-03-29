<?php

namespace Eyika\Atom\Framework\Support\Cache;

use Eyika\Atom\Framework\Support\Cache\Contracts\CacheInterface;
use Eyika\Atom\Framework\Support\Facade\DatabaseConnection;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\InvalidArgumentException;

class DbCache implements CacheInterface
{
    protected string $table;
    protected array $deferredItems = [];

    public function __construct()
    {
        $config = config()->get('cache.stores.database');

        $this->table = $config['table'] ?? '_cache';
    }

    /**
     * @param string $key
     * 
     * @throws InvalidArgumentException
     */
    public function getItem($key): CacheItem
    {
        $value = DatabaseConnection::cache($key, table: $this->table);
        return new CacheItem($key, $value, $value !== null);
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
     * @throws InvalidArgumentException
     */
    public function hasItem(string $key): bool
    {
        return DatabaseConnection::cache($key, table: $this->table) ? true : false;
    }

    /**
     * @throws InvalidArgumentException
     */
    public function clear(): bool
    {
        return DatabaseConnection::clear_cache($this->table);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function deleteItem(string $key): bool
    {
        return DatabaseConnection::uncache($key, $this->table);
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
        return DatabaseConnection::cache($item->getKey(), $item->get(), $item->getExpiration(), $this->table) ? true : false;
    }

    /**
     * @param CacheItem $item
     */
    public function setItem($item): bool
    {
        return $this->save($item);
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
