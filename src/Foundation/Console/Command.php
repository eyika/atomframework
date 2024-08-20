<?php

namespace Eyika\Atom\Framework\Foundation\Console;

use Eyika\Atom\Framework\Exceptions\NotImplementedException;
use Monolog\Level;

abstract class Command
{
    // Store parsed options
    protected array $options;
    protected array $allowedOptions;

    public string $signature = '';

    public function __construct()
    {
        $this->options = [];
        $this->allowedOptions = [];
        $this->parseOptions();
    }

    public function handle(array $arguments = []): bool
    {
        throw new NotImplementedException('method is not implemented');
    }

    // Method to get command line options
    public function option($name)
    {
        return $this->options[$name] ?? null;
    }

    public function setAllowedOptions(array $options)
    {
        $this->allowedOptions = $options;
    }
    
    // Method to parse command-line options
    protected function parseOptions()
    {
        global $argv;

        foreach ($argv as $arg) {
            // Match options in the form --option=value
            if (preg_match('/^--(\w+)=?(.*)$/', $arg, $matches)) {
                $name = $matches[1];
                $value = $matches[2];

                // If value is not provided, set it as true
                if ($value === '') {
                    $value = true;
                }

                // Store the option in the $options array
                if (
                    !in_array("{--$name=}", $this->allowedOptions) &&
                    !in_array("{--$name}", $this->allowedOptions) &&
                    !in_array("{-$name=}", $this->allowedOptions) &&
                    !in_array("{-$name}", $this->allowedOptions)
                ) {
                    //TODO: throw an exception with exit code 1 telling that the command option is not supported
                }
                $this->options[$name] = $value;
            }
        }
    }

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

    public function warn(string $message, array $context = [], $to_log_file = false)
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