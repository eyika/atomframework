<?php

namespace Eyika\Atom\Framework\Foundation\Contracts;

use Eyika\Atom\Framework\Foundation\Console\Command;

interface ConsoleKernel
{
    public function register(string $name, Command|callable $command, array $options);

    public function purpose(string $purpose);

    public function comment(string $comment);

    public function run(string $name, array $arguments = []);

    public function terminate($inputs = []): int;
}
