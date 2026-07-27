<?php

namespace Eyika\Atom\Framework\Broadcasting\Drivers;

use Eyika\Atom\Framework\Foundation\Broadcasting\Contracts\BroadcastInterface;

class LogBroadcaster implements BroadcastInterface
{
    public function broadcast(array $channels, $event, array $payload = [])
    {
        info("Broadcasting event [$event] on channels: " . implode(', ', $channels), $payload);
    }
}
