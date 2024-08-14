<?php

namespace Basttyy\FxDataServer\libs\Traits;

use Basttyy\FxDataServer\libs\Interfaces\ModelInterface;
use Basttyy\FxDataServer\libs\Interfaces\UserModelInterface;

trait InitsModelEvents
{
    public static function boot(ModelInterface | UserModelInterface | null $model, string $event)
    {
    }

    public static function booting(ModelInterface | UserModelInterface | null $model, string $event)
    {
    }

    public static function booted(ModelInterface | UserModelInterface | null $model, string $event)
    {
    }

    public static function creating($model, string $event, callable $callback)
    {
        if ($event == 'creating')
            $callback($model);
    }

    public static function created($model, string $event, callable $callback)
    {
        if ($event == 'created')
            $callback($model);
    }

    public static function saving($model, string $event, callable $callback)
    {
        if ($event == 'saving')
            $callback($model);
    }

    public static function saved($model, string $event, callable $callback)
    {
        if ($event == 'saved')
            $callback($model);
    }

    public static function deleting($model, string $event, callable $callback)
    {
        if ($event == 'deleting')
            $callback($model);
    }

    public static function deleted($model, string $event, callable $callback)
    {
        if ($event == 'deleted')
            $callback($model);
    }
}