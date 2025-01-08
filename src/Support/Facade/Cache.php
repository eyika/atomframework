<?php

namespace Eyika\Atom\Framework\Support\Facade;

use Eyika\Atom\Framework\Support\Cache\Cache as CacheCache;

/**
 * @method static Eyika\Atom\Framework\Support\Cache\CacheItem function getItem(string $key)
 * @method static iterable function getItems(array $keys = [])
 * @method static bool function hasItem(string $key)
 * @method static bool function clear()
 * @method static bool deleteItem(string $key)
 * @method static bool deleteItems(array $keys)
 * @method static save($item)
 * @method static bool saveDeferred($item)
 * @method static bool commit()
 */
class Cache extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'cache';
    }
}
