<?php

namespace Eyika\Atom\Framework\Foundation\Event;

use Eyika\Atom\Framework\Broadcasting\Contracts\ShouldBroadcast;
use Eyika\Atom\Framework\Support\Facade\Broadcast;

/**
 * Event dispatcher. Handles both OBJECT events (dispatch($eventObject) — the event's
 * class name is the event name and it is the payload) and STRING events with an
 * explicit payload (dispatch('user.registered', [$user])). Supports wildcard listener
 * patterns ('model.*'), closure / "Class@method" / invokable-class / [obj,'method']
 * listeners, subscriber classes, and halt-on-first-response via until().
 */
class Dispatcher
{
    /** @var array<string, array<callable|string|array>> exact event => listeners */
    protected array $listeners = [];

    /** @var array<string, array<callable|string|array>> wildcard pattern => listeners */
    protected array $wildcards = [];

    /**
     * Register a listener for one or more events (wildcards allowed, e.g. 'model.*').
     *
     * @param string|string[] $events
     */
    public function listen(string|array $events, callable|string|array $listener): void
    {
        foreach ((array) $events as $event) {
            if (str_contains($event, '*')) {
                $this->wildcards[$event][] = $listener;
            } else {
                $this->listeners[$event][] = $listener;
            }
        }
    }

    /** Are there any listeners (exact or wildcard) for the event? */
    public function hasListeners(string $eventName): bool
    {
        if (!empty($this->listeners[$eventName])) {
            return true;
        }
        foreach (array_keys($this->wildcards) as $pattern) {
            if ($this->patternMatches($pattern, $eventName)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Dispatch an event to its listeners.
     *
     * @param string|object $event   Event object, or a string event name.
     * @param mixed         $payload Payload for a string event (wrapped in an array).
     * @param bool          $halt    Return the first non-null listener response and stop.
     * @return array|mixed           Listener responses (or the first non-null when $halt).
     */
    public function dispatch(string|object $event, mixed $payload = [], bool $halt = false): mixed
    {
        [$eventName, $payload, $eventObject] = $this->parseEventAndPayload($event, $payload);

        $responses = [];
        foreach ($this->getListenersFor($eventName) as $listener) {
            $response = $this->callListener($listener, $payload);

            // until()/halt: first non-null response wins and stops propagation.
            if ($halt && $response !== null) {
                return $response;
            }
            // A listener returning exactly false halts propagation (Laravel semantics).
            if ($response === false) {
                break;
            }
            $responses[] = $response;
        }

        if ($eventObject instanceof ShouldBroadcast) {
            Broadcast::broadcast($eventObject->broadcastOn(), get_class($eventObject), get_object_vars($eventObject));
        }

        return $halt ? null : $responses;
    }

    /** Dispatch and return the first non-null listener response (for "before" hooks). */
    public function until(string|object $event, mixed $payload = []): mixed
    {
        return $this->dispatch($event, $payload, true);
    }

    /** Remove all listeners for an event (exact or wildcard pattern). */
    public function forget(string $event): void
    {
        unset($this->listeners[$event], $this->wildcards[$event]);
    }

    /**
     * Register a subscriber: a class with a subscribe(Dispatcher $events) method that
     * wires up its own listeners.
     */
    public function subscribe(string|object $subscriber): void
    {
        $subscriber = is_string($subscriber) ? new $subscriber() : $subscriber;
        $subscriber->subscribe($this);
    }

    /**
     * Register multiple event => listener(s) mappings at once.
     *
     * @param array<string, callable|string|array<callable|string>> $events
     */
    public function registerListeners(array $events): void
    {
        foreach ($events as $event => $listeners) {
            foreach ((array) $listeners as $listener) {
                $this->listen($event, $listener);
            }
        }
    }

    /**
     * @return array{0:string,1:array,2:?object} [event name, payload args, event object]
     */
    protected function parseEventAndPayload(string|object $event, mixed $payload): array
    {
        if (is_object($event)) {
            return [get_class($event), [$event], $event];
        }
        return [$event, is_array($payload) ? array_values($payload) : [$payload], null];
    }

    /** Exact listeners plus any whose wildcard pattern matches the event name. */
    protected function getListenersFor(string $eventName): array
    {
        $listeners = $this->listeners[$eventName] ?? [];
        foreach ($this->wildcards as $pattern => $wild) {
            if ($this->patternMatches($pattern, $eventName)) {
                $listeners = array_merge($listeners, $wild);
            }
        }
        return $listeners;
    }

    /** Invoke a listener (closure, "Class@method", invokable/handle class, or [obj,'m']). */
    protected function callListener(callable|string|array $listener, array $payload): mixed
    {
        if (is_string($listener)) {
            if (str_contains($listener, '@')) {
                [$class, $method] = explode('@', $listener, 2);
                $listener = [new $class(), $method];
            } elseif (class_exists($listener)) {
                $instance = new $listener();
                $listener = [$instance, method_exists($instance, 'handle') ? 'handle' : '__invoke'];
            }
        }

        return $listener(...$payload);
    }

    /** Match a wildcard pattern (only '*' is special) against an event name. */
    protected function patternMatches(string $pattern, string $eventName): bool
    {
        $regex = '/^' . str_replace('\*', '.*', preg_quote($pattern, '/')) . '$/';
        return (bool) preg_match($regex, $eventName);
    }
}
