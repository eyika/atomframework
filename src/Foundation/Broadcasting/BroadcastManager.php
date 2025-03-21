<?php

namespace Eyika\Atom\Framework\Foundation\Broadcasting;

use Closure;
use Eyika\Atom\Framework\Broadcasting\Drivers\LogBroadcaster;
use Eyika\Atom\Framework\Broadcasting\Drivers\PusherBroadcaster;
use Eyika\Atom\Framework\Foundation\Broadcasting\Contracts\BroadcastInterface;
use InvalidArgumentException;

class BroadcastManager
{
    protected $app;
    /** @property BroadcastInterface[] $drivers */
    protected $drivers = [];

    protected static array $channels = [];

    public function __construct($app)
    {
        $this->app = $app;
    }

    public function driver($name = null): BroadcastInterface
    {
        $name = $name ?: config('broadcasting.default');

        if (!isset($this->drivers[$name])) {
            $this->drivers[$name] = $this->resolve($name);
        }

        return $this->drivers[$name];
    }

    protected function resolve($name)
    {
        switch ($name) {
            case 'log':
                return new LogBroadcaster();
            case 'pusher':
                return new PusherBroadcaster(config('broadcasting.connections.pusher'));
            default:
                throw new InvalidArgumentException("Unsupported broadcast driver [$name]");
        }
    }

    /**
     * Register a broadcast channel authorization callback.
     */
    public static function channel(string $channel, Closure $callback)
    {
        static::$channels[$channel] = $callback;
    }

    /**
     * Authenticate a user for a private channel subscription.
     */
    public static function authenticate($user, string $channel)
    {
        foreach (static::$channels as $pattern => $callback) {
            $regex = '/^' . preg_replace('/\{(\w+)\}/', '(?P<$1>[^.]+)', preg_quote($pattern, '/')) . '$/';

            if (preg_match($regex, $channel, $matches)) {
                unset($matches[0]); // Remove the full match
                return call_user_func($callback, $user, ...array_values($matches));
            }
        }

        return false;
    }
}
