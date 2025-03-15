<?php

namespace Eyika\Atom\Framework\Foundation\Contracts;

use Eyika\Atom\Framework\Foundation\Console\Command;

interface ConsoleKernel
{
    /**
     * Register a command to the list of available console commands
     */
    public function register(string $signature, Command|callable $command, array $options);

    /**
     * Register the purpose of a command
     */
    public function purpose(string $purpose);

    /**
     * Log a comment to the terminal
     */
    public function comment(string $comment);

    /**
     * Execute a specified command from the list of commands in the kernel
     */
    public function run(string $signature, array $arguments = [], bool $requireConsoleRoute = false);

    /**
     * Terminate the execution of a command and return its value
     */
    public function terminate($inputs = []): int;

    /**
     * Register scheduled commands in the sheduler
     */
    public function schedule(): void;
}
