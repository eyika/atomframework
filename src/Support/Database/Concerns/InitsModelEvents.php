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
     * The model lifecycle events an observer may hook. A "before" event
     * (creating/updating/saving/deleting/restoring) returning false aborts the write.
     *
     * @return string[]
     */
    public static function observableEvents(): array
    {
        return [
            'retrieved', 'creating', 'created', 'updating', 'updated',
            'saving', 'saved', 'deleting', 'deleted', 'restoring', 'restored',
        ];
    }

    /**
     * Register one or more OBSERVERS for this model. An observer is a class (or
     * instance) with methods named after lifecycle events — creating(), created(),
     * updating(), deleting(), … each receiving the model. Only the methods it actually
     * defines are wired up.
     *
     *   User::observe(UserObserver::class);
     *
     * @param string|object|array<string|object> $observers
     */
    public static function observe(string|object|array $observers): void
    {
        foreach ((array) $observers as $observer) {
            $instance = is_string($observer) ? new $observer() : $observer;

            foreach (static::observableEvents() as $event) {
                if (method_exists($instance, $event)) {
                    static::on($event, [$instance, $event]);
                }
            }
        }
    }

    /**
     * Remove all registered listeners for this model class (test isolation).
     */
    public static function flushEventListeners(): void
    {
        unset(static::$eventListeners[static::class]);
    }
}
