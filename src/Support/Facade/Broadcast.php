<?php

namespace Eyika\Atom\Framework\Support\Facade;

use Eyika\Atom\Framework\Foundation\Broadcasting\Contracts\BroadcastInterface;
use Eyika\Atom\Framework\Support\Database\Connection;

/**
 * @method static BroadcastInterface driver($name = null)
 */
class Broadcast extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'broadcast.manager';
    }
}
