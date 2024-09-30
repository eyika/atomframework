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

    public static function run(string $name, $arguments = [])
    {
        Console::run($name, $arguments);
        return new static;
    }

    public static function terminate($arguments = [])
    {
        exit(Console::terminate($arguments));
    }
}
