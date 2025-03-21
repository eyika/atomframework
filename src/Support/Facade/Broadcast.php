<?php

namespace Eyika\Atom\Framework\Support\Facade;

use Eyika\Atom\Framework\Foundation\Broadcasting\Contracts\BroadcastInterface;

/**
 * @method static BroadcastInterface driver($name = null)
 * @method static void broadcast(array $channels, $event, array $payload = [])
 */
class Broadcast extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'broadcast.manager';
    }
}
