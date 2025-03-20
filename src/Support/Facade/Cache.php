<?php

namespace Eyika\Atom\Framework\Support\Facade;

use Eyika\Atom\Framework\Support\Cache\CacheItem;

/**
 * @method static CacheItem getItem(string $key)
 * @method static iterable getItems(array $keys = [])
 * @method static bool hasItem(string $key)
 * @method static bool clear()
 * @method static bool deleteItem(string $key)
 * @method static bool deleteItems(array $keys)
 * @method static save($item)
 * @method static bool saveDeferred($item)
 * @method static bool commit()
 * @method static self instance()
 */
class Cache extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'cache';
    }
}
