<?php

namespace Eyika\Atom\Framework\Support\Facade;

use Eyika\Atom\Framework\Foundation\Contracts\ConsoleKernel;

/**
 * @method static \Eyika\Atom\Framework\Foundation\Console\Scheduler command(string $name, string $expression = null)
 * @method static \Eyika\Atom\Framework\Foundation\Console\Scheduler expression(string $expression)
 * @method static \Eyika\Atom\Framework\Foundation\Console\Scheduler hourly()
 * @method static \Eyika\Atom\Framework\Foundation\Console\Scheduler daily()
 * @method static \Eyika\Atom\Framework\Foundation\Console\Scheduler midnight()
 * @method static \Eyika\Atom\Framework\Foundation\Console\Scheduler weekly()
 * @method static \Eyika\Atom\Framework\Foundation\Console\Scheduler monthly()
 * @method static \Eyika\Atom\Framework\Foundation\Console\Scheduler yearly()
 * @method static \Eyika\Atom\Framework\Foundation\Console\Scheduler annually()
 * @method static void run()
 */
class Scheduler extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'scheduler';
    }
}
