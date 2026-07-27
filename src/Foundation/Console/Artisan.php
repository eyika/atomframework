<?php

namespace Eyika\Atom\Framework\Foundation\Console;

use Eyika\Atom\Framework\Support\Facade\Console;

class Artisan
{
    public static function command(string $name, callable|Command $command, array $options = []): self
    {
        Console::register($name, $command, $options);
        return new static;
    }

    public static function purpose(string $purpose)
    {
        Console::purpose($purpose);
    }

    public static function run(string $name, $arguments = [], bool $requireConsoleRoute = false)
    {
        Console::run($name, $arguments, $requireConsoleRoute);
        return new static;
    }

    public static function call(string $name, $arguments = [], bool $requireConsoleRoute = false)
    {
        return static::run($name, $arguments, $requireConsoleRoute);
    }

    public static function terminate($arguments = [])
    {
        exit(Console::terminate($arguments));
    }
}
