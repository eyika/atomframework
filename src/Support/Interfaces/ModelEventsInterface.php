<?php

namespace Basttyy\FxDataServer\libs\Interfaces;

interface ModelEventsInterface
{
    public static function boot(ModelInterface | UserModelInterface | null $model, string $event);

    public static function booting(ModelInterface | UserModelInterface | null $model, string $event);

    public static function booted(ModelInterface | UserModelInterface | null $model, string $event);

    public static function creating($model, string $event, callable $callback);

    public static function created($model, string $event, callable $callback);

    public static function saving($model, string $event, callable $callback);

    public static function saved($model, string $event, callable $callback);

    public static function deleting($model, string $event, callable $callback);

    public static function deleted($model, string $event, callable $callback);
}