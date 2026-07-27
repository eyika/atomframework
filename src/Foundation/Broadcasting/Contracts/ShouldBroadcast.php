<?php

namespace Eyika\Atom\Framework\Broadcasting\Contracts;

interface ShouldBroadcast
{
    public function broadcastOn(): array;
}
