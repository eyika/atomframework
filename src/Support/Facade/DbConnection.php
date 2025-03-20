<?php

namespace Eyika\Atom\Framework\Support\Facade;

use Eyika\Atom\Framework\Support\Database\Connection;

/**
 * @method static Connection connect()
 * @method static Connection instance()
 */
class DbConnection extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'blade';
    }
}
