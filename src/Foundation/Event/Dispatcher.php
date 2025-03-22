<?php

namespace Eyika\Atom\Framework\Foundation\Event;

use Eyika\Atom\Framework\Broadcasting\Contracts\ShouldBroadcast;
use Eyika\Atom\Framework\Support\Facade\Broadcast;

class Dispatcher
{
    /**
     * Stores the registered events and their listeners.
     *
     * @var array
     */
    protected array $listeners = [];

    /**
     * Registers a listener for an event.
     *
     * @param string   $event
     * @param callable|string $listener
     */
    public function listen(string $event, callable|string $listener): void
    {
        $this->listeners[$event][] = $listener;
    }

    /**
     * Dispatch an event and call its listeners.
     *
     * @param object $event
     */
    public function dispatch(object $event): void
    {
        $eventName = get_class($event);

        if (!isset($this->listeners[$eventName])) {
            return;
        }

        foreach ($this->listeners[$eventName] as $listener) {
            if (is_callable($listener)) {
                $listener($event);
            } elseif (class_exists($listener)) {
                (new $listener())->handle($event);
            }

            if ($event instanceof ShouldBroadcast) {
                Broadcast::broadcast($event->broadcastOn(), get_class($event), get_object_vars($event));
            }
        }
    }

    /**
     * Registers multiple listeners.
     *
     * @param array $events
     */
    public function registerListeners(array $events): void
    {
        foreach ($events as $event => $listeners) {
            foreach ((array) $listeners as $listener) {
                $this->listen($event, $listener);
            }
        }
    }
}
