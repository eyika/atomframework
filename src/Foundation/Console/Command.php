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

    public string $description = '';
    public string $signature = '';

    public function __construct()
    {
        $this->directory = '';
        $this->options = [];
        $this->allowedOptions = [];
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
        $this->parseOptions();
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

    /**
     * Read a named option, e.g. `--workers=4` as `option('workers')`.
     *
     * Options are NOT readable off `arguments()`, which is the raw argv list and numerically
     * indexed — `$arguments['workers']` finds nothing, and with a `?? $default` beside it that is
     * silent. Flags come through here; positional tokens come through `argument(int $index)`.
     *
     * The return type used to be `null|string`, which **coerced the caller's own default**:
     * `option('workers', 32)` handed back the string `"32"`, and `option('force', false)` handed
     * back `""`. A value the caller supplied should come back as the caller wrote it, so the
     * default now passes through untouched. A flag given without `=` is `true`.
     */
    public function option($name, $default = null): mixed
    {
        return $this->options[$name] ?? $default;
    }

    // Method to get command line options
    public function options(): array
    {
        return $this->options;
    }

    // Method to set command line options
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
        // global $argv;
        $this->options = [];

        foreach ($this->arguments as $arg) {
            // Match options in the form --option=value, --option, -option, -option=
            if (preg_match('/^-{1,2}([\w-]+)(?:=(.*))?$/', $arg, $matches)) {
                $name = $matches[1];
                $value = isset($matches[2]) ? $matches[2] : (strpos($name, '=') === false ? true : null); // Set value to true if not provided
        
                // Store the option in the $options array only if it's allowed
                if (
                    !in_array("{--$name=}", $this->allowedOptions) &&
                    !in_array("{--$name}", $this->allowedOptions) &&
                    !in_array("{-$name=}", $this->allowedOptions) &&
                    !in_array("{-$name}", $this->allowedOptions)
                ) {
                    // TODO: throw an exception with exit code 1 telling that the command option is not supported
                }
                
                $this->options[$name] = $value;
            }
        }
    }

    /**
     * Write an unadorned line to the console.
     *
     * `info()` already emits console output without a level or a timestamp — it is not the
     * log-shaped thing its name suggests — but `line()` is what a reader reaches for when printing
     * a table of numbers rather than announcing an event, and its absence sent one consumer to
     * `STDOUT` directly. Same output, name that says what it does.
     */
    protected function line(string $message = ''): void
    {
        $this->info($message);
    }

    /** Blank lines, for separating sections of output. */
    protected function newLine(int $count = 1): void
    {
        for ($i = 0; $i < max(1, $count); $i++) {
            $this->line('');
        }
    }

    protected function call(string $name, array $arguments = [], bool $requireConsoleRoute = false)
    {
        Artisan::call($name, $arguments, $requireConsoleRoute);
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
