<?php

namespace Eyika\Atom\Framework\Broadcasting\Drivers;

use Eyika\Atom\Framework\Foundation\Broadcasting\Contracts\BroadcastInterface;
use Predis\Client;

/**
 * This implementation is very incompleted and should not be used
 */

class RedisBroadcastDriver implements BroadcastInterface
{
    protected Client $redis;
    protected string $channelPrefix = 'broadcast:';

    public function __construct(array $config)
    {
        $this->redis = new Client($config['redis']);
    }

    /**
     * Broadcast event to Redis Pub/Sub channel.
     */
    public function broadcast(array $channels, $event, array $payload = [])
    {
        foreach ($channels as $channel) {
            $this->redis->publish($this->channelPrefix . $channel, json_encode($payload));
        }
    }
}
