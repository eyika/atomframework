<?php

namespace Eyika\Atom\Framework\Support\Facade;

/**
 * @method static \Eyika\Atom\Framework\Foundation\Console\Scheduler command(string $signature, array $arguements = [], string|null $expression = null)
 * @method static void run()
 */
class Scheduler extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'scheduler';
    }
}
