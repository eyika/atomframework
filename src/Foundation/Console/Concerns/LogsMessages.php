<?php
namespace Eyika\Atom\Framework\Foundation\Console\Concerns;

trait LogsMessages
{
    public function info(string $message, array $context = [], $to_log_file = false): void
    {
        if ($to_log_file) {
            info($message, $context);
        } else {
            logger(isConsole: true)->info($message);
        }
    }

    public function error(string $message, array $context = [], $to_log_file = false): void
    {
        if ($to_log_file) {
            error($message, $context);
        } else {
            logger(isConsole: true)->error($message);
        }
    }

    public function notice(string $message, array $context = [], $to_log_file = false): void
    {
        if ($to_log_file) {
            notice($message, $context);
        } else {
            logger(isConsole: true)->notice($message);
        }
    }

    public function emergency(string $message, array $context = [], $to_log_file = false): void
    {
        if ($to_log_file) {
            emergency($message, $context);
        } else {
            logger(isConsole: true)->emergency($message);
        }
    }

    public function warning(string $message, array $context = [], $to_log_file = false): void
    {
        if ($to_log_file) {
            warning($message, $context);
        } else {
            logger(isConsole: true)->warning($message);
        }
    }

    public function warn(string $message, array $context = [], $to_log_file = false): void
    {
        $this->warning($message, $context, $to_log_file);
    }

    public function debug(string $message, array $context = [], $to_log_file = false): void
    {
        if ($to_log_file) {
            debug($message, $context);
        } else {
            logger(isConsole: true)->debug($message);
        }
    }

    public function critical(string $message, array $context = [], $to_log_file = false): void
    {
        if ($to_log_file) {
            critical($message, $context);
        } else {
            logger(isConsole: true)->critical($message);
        }
    }
}
