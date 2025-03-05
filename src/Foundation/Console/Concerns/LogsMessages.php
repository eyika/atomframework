<?php
namespace Eyika\Atom\Framework\Foundation\Console\Concerns;

use Eyika\Atom\Framework\Foundation\Console\ConsoleColorizer;
use Monolog\Handler\StreamHandler;
use Monolog\Level;

trait LogsMessages
{
    public function info(string $message, array $context = [], $to_log_file = false): void
    {
        if ($to_log_file) {
            info($message, $context);
        } else {
            // Use custom formatter
            $formatter = new ConsoleColorizer("[%datetime%] %channel%.%level_name%: %message%\n");

            $handler = new StreamHandler('php://stdout', Level::Debug);
            $handler->setFormatter($formatter);

            logger(name: 'console')->pushHandler($handler)->info($message);
        }
    }

    public function error(string $message, array $context = [], $to_log_file = false): void
    {
        if ($to_log_file) {
            error($message, $context);
        } else {
            // Use custom formatter
            $formatter = new ConsoleColorizer("[%datetime%] %channel%.%level_name%: %message%\n");

            $handler = new StreamHandler('php://stdout', Level::Error);
            $handler->setFormatter($formatter);

            logger(name: 'console')->pushHandler($handler)->error($message);
        }
    }

    public function notice(string $message, array $context = [], $to_log_file = false): void
    {
        if ($to_log_file) {
            notice($message, $context);
        } else {
            // Use custom formatter
            $formatter = new ConsoleColorizer("[%datetime%] %channel%.%level_name%: %message%\n");

            $handler = new StreamHandler('php://stdout', Level::Notice);
            $handler->setFormatter($formatter);

            logger(name: 'console')->pushHandler($handler)->notice($message);
        }
    }

    public function emergency(string $message, array $context = [], $to_log_file = false): void
    {
        if ($to_log_file) {
            emergency($message, $context);
        } else {
            // Use custom formatter
            $formatter = new ConsoleColorizer("[%datetime%] %channel%.%level_name%: %message%\n");

            $handler = new StreamHandler('php://stdout', Level::Emergency);
            $handler->setFormatter($formatter);

            logger(name: 'console')->pushHandler($handler)->emergency($message);
        }
    }

    public function warning(string $message, array $context = [], $to_log_file = false): void
    {
        if ($to_log_file) {
            warning($message, $context);
        } else {
            // Use custom formatter
            $formatter = new ConsoleColorizer("[%datetime%] %channel%.%level_name%: %message%\n");

            $handler = new StreamHandler('php://stdout', Level::Warning);
            $handler->setFormatter($formatter);

            logger(name: 'console')->pushHandler($handler)->warning($message);
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
            // Use custom formatter
            $formatter = new ConsoleColorizer("[%datetime%] %channel%.%level_name%: %message%\n");

            $handler = new StreamHandler('php://stdout', Level::Debug);
            $handler->setFormatter($formatter);

            logger(name: 'console')->pushHandler($handler)->debug($message);
        }
    }

    public function critical(string $message, array $context = [], $to_log_file = false): void
    {
        if ($to_log_file) {
            critical($message, $context);
        } else {
            // Use custom formatter
            $formatter = new ConsoleColorizer("[%datetime%] %channel%.%level_name%: %message%\n");

            $handler = new StreamHandler('php://stdout', Level::Critical);
            $handler->setFormatter($formatter);

            logger(name: 'console')->pushHandler($handler)->critical($message);
        }
    }
}