<?php

namespace Eyika\Atom\Framework\Support\Facade;

/**
 * @method static string encrypt(mixed $value, bool $serialize = true)
 * @method static mixed decrypt(string $payload, bool $unserialize = true)
 */
class Encrypter extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'encrypter';
    }
}
