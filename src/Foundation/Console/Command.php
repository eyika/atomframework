<?php

namespace Eyika\Atom\Framework\Foundation\Console;

use Eyika\Atom\Framework\Exceptions\Console\BaseConsoleException;
use Eyika\Atom\Framework\Exceptions\NotImplementedException;
use Eyika\Atom\Framework\Foundation\Console\Concerns\LogsMessages;
use Eyika\Atom\Framework\Foundation\Console\Contracts\ShouldLogMessages;
use Eyika\Atom\Framework\Support\Arr;

abstract class Command implements ShouldLogMessages
{
    use LogsMessages;

    // Store parsed options
    protected array $options;
    protected array $allowedOptions;
    protected array $arguments;
    protected string $directory;

    public string $signature = '';

    public function __construct()
    {
        $this->directory = '';
        $this->options = [];
        $this->allowedOptions = [];
        $this->parseOptions();
    }

    public function handle(): bool
    {
        throw new NotImplementedException('method is not implemented');
    }

    public function setBaseDir(string $directory)
    {
        $this->directory = $directory;
        return $this;
    }

    public function setArguments(array $arguments = [])
    {
        $this->arguments = $arguments;
        return $this;
    }

    public function arguments()
    {
        return $this->arguments;
    }

    public function argument(int $index): null|string
    {
        if (!array_key_exists($index, $this->arguments)) {
            return null;
        }
        return $this->arguments[$index];
    }

    // Method to get command line options
    public function option($name): null|string
    {
        return $this->options[$name] ?? null;
    }

    // Method to get command line options
    public function setOption($name, $value)
    {
        if (!Arr::keyExists($this->allowedOptions, $name)) {
            throw new BaseConsoleException("option $name is not in allowed options for this commmand");
        }

        $this->options[$name] = $value;
    }

    public function setAllowedOptions(array $options)
    {
        $this->allowedOptions = $options;
        return $this;
    }

    public function allowedOptions()
    {
        return $this->allowedOptions;
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

    protected function call(string $name, array $arguments = [])
    {
        Artisan::call($name, $arguments, true);
    }

    protected function table(array $headers, array $rows)
    {
        $columnWidths = array_map('strlen', $headers);

        foreach ($rows as $row) {
            foreach ($row as $i => $cell) {
                $columnWidths[$i] = max($columnWidths[$i], strlen($cell));
            }
        }

        $separator = '+-' . implode('-+-', array_map(fn ($w) => str_repeat('-', $w), $columnWidths)) . '-+';
        $headerRow = '| ' . implode(' | ', array_map(fn ($h, $w) => str_pad($h, $w), $headers, $columnWidths)) . ' |';

        $output = [$separator, $headerRow, $separator];

        foreach ($rows as $row) {
            $output[] = '| ' . implode(' | ', array_map(fn ($cell, $w) => str_pad($cell, $w), $row, $columnWidths)) . ' |';
        }

        $output[] = $separator;

        $this->info(implode("\n", $output));
    }
}
