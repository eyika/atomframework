<?php

namespace Eyika\Atom\Framework\Support\Facade;

/**
 * @method static string make(string $value, array $options = [])
 * @method static bool check(string $value, ?string $hashedValue)
 * @method static bool needsRehash(string $hashedValue, array $options = [])
 * @method static array|null info(string $hashedValue)
 */
class Hash extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'hash';
    }
}
