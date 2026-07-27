<?php

namespace Eyika\Atom\Framework\Support\Facade;

/**
 * @method static void  listen(string|array $events, callable|string|array $listener)
 * @method static mixed dispatch(string|object $event, mixed $payload = [], bool $halt = false)
 * @method static mixed until(string|object $event, mixed $payload = [])
 * @method static bool  hasListeners(string $eventName)
 * @method static void  forget(string $event)
 * @method static void  subscribe(string|object $subscriber)
 * @method static void  registerListeners(array $events)
 *
 * @see \Eyika\Atom\Framework\Foundation\Event\Dispatcher
 */
class Event extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'events';
    }
}
