<?php
namespace Eyika\Atom\Framework\Foundation\Console\Concerns;

use Eyika\Atom\Framework\Exceptions\MethodNotFoundException;
use Eyika\Atom\Framework\Foundation\Application;
use Eyika\Atom\Framework\Support\Arr;

trait RunsOnConsole
{
    // Function to execute the command and display output in real-time
    function executeCommand($options = [], string $type = 'phpUnit')
    {
        if (!method_exists($this, "{$type}Commander"))
        {
            throw new MethodNotFoundException("method {$type}Commander not found");
        }

        $command = $this->{"{$type}Commander"}($options);
        $env = Arr::only($GLOBALS, Arr::values(Application::GLOBAL_VARS));
        $env = array_merge($_ENV, $env, getenv());

        // Let the child process inherit our stdout/stderr directly. This streams
        // output in real time on every platform. The previous approach pumped the
        // output via non-blocking reads on proc_open pipes, which PHP does not
        // support on Windows (stream_set_blocking() is a no-op there), so fgets()
        // would block forever and commands like `serve`/`migrate` appeared to hang.
        $process = proc_open($command, [
            1 => STDOUT, // stdout
            2 => STDERR, // stderr
        ], $pipes, null, $env);

        if (is_resource($process)) {
            return proc_close($process);
        } else {
            return 1; // Error running the command
        }
    }

    /**
     * The interpreter running us, quoted. PHP_BINARY is the exact binary in use (so a child
     * process can't pick up a different `php` from PATH), and it is quoted because its own
     * path may contain spaces — e.g. C:\Program Files\php\php.exe.
     */
    protected function phpBinary(): string
    {
        return escapeshellarg(PHP_BINARY ?: 'php');
    }

    /** Quote each argument so a value containing a space survives as ONE argument. */
    protected function quoteArgs(array $options): string
    {
        return implode(' ', array_map(
            static fn ($option) => escapeshellarg((string) $option),
            $options
        ));
    }

    function phpInbuiltServerCommander($options = [])
    {
        $kv_options = [];
        $found = [];

        Arr::each($options, function ($key, $option) use (&$found, &$kv_options) {
            if (str_contains($option, '=')) {
                $found[] = $option;
                $v = explode('=', $option);
                $kv_options[$v[0]] = $v[1];
            }
        });
        $options = array_diff($options, $found);

        $address = array_key_exists('--host', $kv_options) || array_key_exists('-a', $kv_options) ? ($kv_options['--host'] ?? $kv_options['-a']) : '0.0.0.0';
        $port = array_key_exists('--port', $kv_options) || array_key_exists('-p', $kv_options) ? ($kv_options['--port'] ?? $kv_options['-p']) : '80';
        $timeout = array_key_exists('--timeout', $kv_options) || array_key_exists('-t', $kv_options) ? " -d max_execution_time=". ($kv_options['--timeout'] ?? $kv_options['-t']) : ""; 

        $args = $this->quoteArgs($options);

        // The router script is a quoted argument of its own — previously it was concatenated
        // straight onto the option list with no separator, so any option ran into the path.
        return $this->phpBinary() . "{$timeout} -S {$address}:{$port} -t . "
            . ($args === '' ? '' : $args . ' ')
            . escapeshellarg(base_path('public/index.php'));
    }

    function phpUnitCommander($options = [])
    {
        return $this->phpBinary() . ' '
            . escapeshellarg(base_path('vendor/bin/phpunit')) . ' '
            . $this->quoteArgs($options);
    }
}
