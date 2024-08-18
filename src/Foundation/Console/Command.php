<?php

namespace Eyika\Atom\Framework\Foundation\Console;

use Monolog\Level;

abstract class Command
{
    public string $signature = '';

    abstract public function handle(array $arguments = []): int;

    // public function handle()
    // {
    //     throw new NotImplementedException('method is not implemented');
    // }

    public function info(string $message, array $context = [], $to_log_file = false)
    {
        $to_log_file ? logger()->info($message, $context) : consoleLog(Level::Info, $message);
    }

    public function error(string $message, array $context = [], $to_log_file = false)
    {
        $to_log_file ? logger()->error($message, $context) : consoleLog(Level::Error, $message);
    }

    public function notice(string $message, array $context = [], $to_log_file = false)
    {
        $to_log_file ? logger()->notice($message, $context) : consoleLog(Level::Notice, $message);
    }

    public function emergency(string $message, array $context = [], $to_log_file = false)
    {
        $to_log_file ? logger()->emergency($message, $context) : consoleLog(Level::Emergency, $message);
    }

    public function warning(string $message, array $context = [], $to_log_file = false)
    {
        $to_log_file ? logger()->warning($message, $context) : consoleLog(Level::Warning, $message);
    }

    public function debug(string $message, array $context = [], $to_log_file = false)
    {
        $to_log_file ? logger()->debug($message, $context) : consoleLog(Level::Debug, $message);
    }

    public function critical(string $message, array $context = [], $to_log_file = false)
    {
        $to_log_file ? logger()->critical($message, $context) : consoleLog(Level::Critical, $message);
    }
}