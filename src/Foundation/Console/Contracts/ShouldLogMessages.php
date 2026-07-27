<?php

namespace Eyika\Atom\Framework\Foundation\Console\Contracts;

interface ShouldLogMessages
{
    public function info(string $message, array $context = [], $to_log_file = false): void;

    public function error(string $message, array $context = [], $to_log_file = false): void;

    public function notice(string $message, array $context = [], $to_log_file = false): void;

    public function emergency(string $message, array $context = [], $to_log_file = false): void;

    public function warning(string $message, array $context = [], $to_log_file = false): void;

    public function warn(string $message, array $context = [], $to_log_file = false): void;

    public function debug(string $message, array $context = [], $to_log_file = false): void;

    public function critical(string $message, array $context = [], $to_log_file = false): void;
}
