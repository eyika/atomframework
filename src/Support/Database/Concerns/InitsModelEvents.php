<?php

namespace Eyika\Atom\Framework\Support\Database\Concerns;

trait InitsModelEvents
{
    /**
     * Registered listeners, keyed by model class then event name.
     *
     * @var array<string, array<string, callable[]>>
     */
    protected static array $eventListeners = [];

    /**
     * Dispatch every listener registered for $event on this model class. A "before"
     * listener (creating/updating/saving/deleting) that returns FALSE aborts the
     * operation; the write paths check this return.
     */
    public static function boot($model, string $event)
    {
        foreach (static::$eventListeners[static::class][$event] ?? [] as $listener) {
            if ($listener($model) === false) {
                return false;
            }
        }
        return true;
    }

    // Reserved hook points (kept for call-site compatibility); dispatch happens in boot().
    public static function booting($model, string $event)
    {
    }

    public static function booted($model, string $event)
    {
    }

    /**
     * Register a listener for an arbitrary event on this model class.
     */
    public static function on(string $event, callable $callback): void
    {
        static::$eventListeners[static::class][$event][] = $callback;
    }

    public static function creating(callable $callback): void
    {
        static::on('creating', $callback);
    }

    public static function created(callable $callback): void
    {
        static::on('created', $callback);
    }

    public static function updating(callable $callback): void
    {
        static::on('updating', $callback);
    }

    public static function updated(callable $callback): void
    {
        static::on('updated', $callback);
    }

    public static function saving(callable $callback): void
    {
        static::on('saving', $callback);
    }

    public static function saved(callable $callback): void
    {
        static::on('saved', $callback);
    }

    public static function deleting(callable $callback): void
    {
        static::on('deleting', $callback);
    }

    public static function deleted(callable $callback): void
    {
        static::on('deleted', $callback);
    }

    public static function retrieved(callable $callback): void
    {
        static::on('retrieved', $callback);
    }

    /**
     * Remove all registered listeners for this model class (test isolation).
     */
    public static function flushEventListeners(): void
    {
        unset(static::$eventListeners[static::class]);
    }
}
