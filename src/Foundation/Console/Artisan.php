<?php

namespace Eyika\Atom\Framework\Foundation\Console;

use Eyika\Atom\Framework\Support\Facade\Console;

class Artisan
{
    public static function command(string $name, callable|Command $command)
    {
        Console::register($name, $command);
    }

    public static function run(string $name, $arguments = [])
    {
        Console::run($name, $arguments);
    }

    public static function terminate($arguments = [])
    {
        exit(Console::terminate($arguments));
    }
}