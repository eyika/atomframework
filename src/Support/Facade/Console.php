<?php

namespace Eyika\Atom\Framework\Support\Facade;

use Eyika\Atom\Framework\Foundation\Contracts\ConsoleKernel;

/**
 * @method static void register(string $name, Command|callable $command, array $options = [])
 * @method static void purpose(string $purpose)
 * @method static void comment(string $comment)
 * @method static void run(string $name, array $arguments = [])
 * @method static int terminate($inputs = [])
 */
class Console extends Facade
{
    protected static function getFacadeAccessor()
    {
        return ConsoleKernel::class;
    }
}
