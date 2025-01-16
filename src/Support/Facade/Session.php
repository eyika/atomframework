<?php

namespace Eyika\Atom\Framework\Support\Facade;

/**
 * @method static save()
 * @method static bool has(string $key)
 * @method static void set(string $key, mixed $value)
 * @method static mixed get(string $key, $default = null)
 * @method static void unset(string $key)
 * @method static void forget(string $key)
 * @method static void start()
 * @method static void destroy()
 * @method static void regenerate()
 */
class Session extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'session';
    }
}
