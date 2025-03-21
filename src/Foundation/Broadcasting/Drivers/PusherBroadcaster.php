<?php

namespace Eyika\Atom\Framework\Broadcasting\Drivers;

use Eyika\Atom\Framework\Foundation\Broadcasting\Contracts\BroadcastInterface;
use Pusher\Pusher;

class PusherBroadcaster implements BroadcastInterface
{
    protected $pusher;

    public function __construct($config)
    {
        $this->pusher = new Pusher(
            $config['key'],
            $config['secret'],
            $config['app_id'],
            ['cluster' => $config['cluster'], 'useTLS' => true]
        );
    }

    public function broadcast(array $channels, $event, array $payload = [])
    {
        foreach ($channels as $channel) {
            $this->pusher->trigger($channel, $event, $payload);
        }
    }
}
