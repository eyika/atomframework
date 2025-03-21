<?php

namespace Eyika\Atom\Framework\Foundation\Broadcasting\Contracts;

interface BroadcastInterface
{
    public function broadcast(array $channels, $event, array $payload = []);
}
