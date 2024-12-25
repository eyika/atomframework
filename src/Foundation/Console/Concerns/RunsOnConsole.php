<?php
namespace Eyika\Atom\Framework\Foundation\Console\Concerns;

use Eyika\Atom\Framework\Exceptions\MethodNotFoundException;
use Eyika\Atom\Framework\Foundation\Application;
use Eyika\Atom\Framework\Support\Arr;

trait RunsOnConsole
{
    // Function to execute the command and display output in real-time
    function executeCommand($options = [], string $type = 'phinx')
    {
        if (!method_exists($this, "{$type}Commander"))
        {
            throw new MethodNotFoundException("method {$type}Commander not found");
        }

        $command = $this->{"{$type}Commander"}($options);
        echo $command;
        $env = Arr::only($GLOBALS, Arr::values(Application::GLOBAL_VARS));
        $env = array_merge($_ENV, $env, getenv());

        $process = proc_open($command, [
            1 => ['pipe', 'w'], // stdout
            2 => ['pipe', 'w'], // stderr
        ], $pipes, null, $env);

        if (is_resource($process)) {
            stream_set_blocking($pipes[1], false); // Set stdout to non-blocking mode
            stream_set_blocking($pipes[2], false); // Set stderr to non-blocking mode
    
            while (!feof($pipes[1]) || !feof($pipes[2])) {
                if ($line = fgets($pipes[1])) {
                    echo "$line";
                }
                if ($line = fgets($pipes[2])) {
                    echo "$line";
                }
    
                usleep(1500); // Sleep for 10ms to prevent high CPU usage
            }
    
            fclose($pipes[1]);
            fclose($pipes[2]);
    
            $return_value = proc_close($process);
            return $return_value;
        } else {
            return 1; // Error running the command
        }
    }

    function phinxCommander($options = [])
    {
        $slash = DIRECTORY_SEPARATOR;
        $config = "-c ". base_path("config/phinx.php");
        if (count($options) > 1) {
            $temp = [$options[0], $config];
            array_push($temp, ...array_slice($options, 1));
            $options = $temp;
        } else {
            $options[] = $config;
        }

        return 'php '. base_path("/vendor/bin/atom_phinx " . implode(' ', $options));
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

        $address = array_key_exists('--address', $kv_options) || array_key_exists('-a', $kv_options) ? ($kv_options['--address'] ?? $kv_options['-a']) : 'localhost';
        $port = array_key_exists('--port', $kv_options) || array_key_exists('-p', $kv_options) ? ($kv_options['--port'] ?? $kv_options['-p']) : '80';

        return "php -S {$address}:{$port} -t . " . implode(' ', $options). base_path("public/index.php");
    }

    function phpUnitCommander($options = [])
    {
        return 'php ' . base_path("vendor/bin/atom_phpunit " . implode(' ', $options));
    }
}
