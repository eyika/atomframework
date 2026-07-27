<?php

namespace Eyika\Atom\Framework\Support\Database\Contracts;

use Eyika\Atom\Framework\Support\Contracts\CanBeDeepCloned;

interface ModelEventsInterface extends CanBeDeepCloned
{
    public static function boot($model, string $event);

    public static function booting($model, string $event);

    public static function booted($model, string $event);

    public static function on(string $event, callable $callback): void;

    public static function creating(callable $callback): void;

    public static function created(callable $callback): void;

    public static function updating(callable $callback): void;

    public static function updated(callable $callback): void;

    public static function saving(callable $callback): void;

    public static function saved(callable $callback): void;

    public static function deleting(callable $callback): void;

    public static function deleted(callable $callback): void;

    public static function retrieved(callable $callback): void;

    /** Register one or more observers (classes with lifecycle-named methods). */
    public static function observe(string|object|array $observers): void;

    /** The lifecycle events an observer may hook. @return string[] */
    public static function observableEvents(): array;

    /** Remove all registered listeners for this model class. */
    public static function flushEventListeners(): void;
}
