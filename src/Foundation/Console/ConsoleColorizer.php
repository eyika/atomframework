<?php

namespace Eyika\Atom\Framework\Foundation\Console;

use Monolog\Formatter\LineFormatter;
use Monolog\LogRecord;
use Psr\Log\LogLevel;

class ConsoleColorizer extends LineFormatter
{
    private array $levelColors = [
        LogLevel::DEBUG => "\033[36m",  // Cyan
        LogLevel::INFO => "\033[32m",   // Green
        LogLevel::NOTICE => "\033[34m", // Blue
        LogLevel::WARNING => "\033[33m", // Yellow
        LogLevel::ERROR => "\033[31m",   // Red
        LogLevel::CRITICAL => "\033[35m", // Magenta
        LogLevel::ALERT => "\033[41m",   // Red Background
        LogLevel::EMERGENCY => "\033[41;97m" // Red Background + White Text
    ];

    public function format(LogRecord $record): string
    {
        $color = $this->levelColors[$record['level']] ?? "\033[0m";
        $reset = "\033[0m";
        return $color . parent::format($record) . $reset;
    }
}