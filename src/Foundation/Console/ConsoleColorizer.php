<?php

namespace Eyika\Atom\Framework\Foundation\Console;

use Monolog\Formatter\LineFormatter;
use Monolog\LogRecord;
use Psr\Log\LogLevel;

class ConsoleColorizer extends LineFormatter
{
    private array $levelColors = [
        100 => "\033[36m",  // Cyan
        200 => "\033[32m",   // Green
        250 => "\033[34m", // Blue
        300 => "\033[33m", // Yellow
        400 => "\033[31m",   // Red
        500 => "\033[35m", // Magenta
        550 => "\033[41m",   // Red Background
        600 => "\033[41;97m" // Red Background + White Text
    ];

    public function format(LogRecord $record): string
    {
        $color = $this->levelColors[$record['level']] ?? "\033[0m";
        $reset = "\033[0m";
        return $color . parent::format($record) . $reset;
    }
}
