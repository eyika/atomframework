<?php

namespace Eyika\Atom\Framework\Foundation\Contracts;

use Eyika\Atom\Framwork\Foundation\Console\Command;

interface ConsoleKernel
{
    public function register(string $name, Command $command);

    public function run(string $name, array $arguments = []);

    public function terminate($inputs = []);
}