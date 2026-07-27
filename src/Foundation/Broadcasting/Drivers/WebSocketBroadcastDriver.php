<?php

namespace Eyika\Atom\Framework\Broadcasting\Drivers;

use Eyika\Atom\Framework\Foundation\Broadcasting\Contracts\BroadcastInterface;

/**
 * This implementation is very incompleted and should not be used
 */

class WebSocketBroadcastDriver implements BroadcastInterface
{
    protected string $websocketUrl;

    public function __construct(string $websocketUrl)
    {
        $this->websocketUrl = $websocketUrl;
    }

    /**
     * Send event to WebSocket server.
     */
    public function broadcast(array $channels, $event, array $payload = [])
    {
        \Ratchet\Client\connect($this->websocketUrl)->then(function ($conn) use ($payload, $channels) {
            foreach ($channels as $channel) {
                $message = json_encode(['channel' => $channel, 'data' => $payload]);
                $conn->send($message);
            }
            $conn->close();
        }, function ($e) {
            echo "WebSocket Error: " . $e->getMessage() . "\n";
        });
    }
}
