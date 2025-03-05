<?php

namespace Eyika\Atom\Framework\Foundation\Console;

use Monolog\Formatter\LineFormatter;
use Monolog\Level;
use Monolog\LogRecord;

class ConsoleColorizer extends LineFormatter
{
    private array $levelColors = [
        Level::Debug => "\033[36m",  // Cyan
        Level::Info => "\033[32m",   // Green
        Level::Notice => "\033[34m", // Blue
        Level::Warning => "\033[33m", // Yellow
        Level::Error => "\033[31m",   // Red
        Level::Critical => "\033[35m", // Magenta
        Level::Alert => "\033[41m",   // Red Background
        Level::Emergency => "\033[41;97m" // Red Background + White Text
    ];

    public function format(LogRecord $record): string
    {
        $color = $this->levelColors[$record['level']] ?? "\033[0m";
        $reset = "\033[0m";
        return $color . parent::format($record) . $reset;
    }
}