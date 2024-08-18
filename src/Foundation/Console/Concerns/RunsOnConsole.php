<?php
namespace Eyika\Atom\Framework\Foundation\Console\Concerns;

use Eyika\Atom\Framework\Exceptions\MethodNotFoundException;
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

        $process = proc_open($command, [
            1 => ['pipe', 'w'], // stdout
            2 => ['pipe', 'w'], // stderr
        ], $pipes);

        if (is_resource($process)) {
            stream_set_blocking($pipes[1], false); // Set stdout to non-blocking mode
            stream_set_blocking($pipes[2], false); // Set stderr to non-blocking mode
    
            while (!feof($pipes[1]) || !feof($pipes[2])) {
                if ($line = fgets($pipes[1])) {
                    echo "STDERR: $line";
                }
                if ($line = fgets($pipes[2])) {
                    echo "STDOUT: $line";
                }
    
                usleep(300000); // Sleep for 100ms to prevent high CPU usage
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
        $config = "-c ". base_path(). "config/phinx.php";
        if (count($options) > 1) {
            $temp = $options[1];
            $options[1] = $config;
            $options[] = $temp;
        } else {
            $options[] = $config;
        }

        return base_path().$slash.'vendor'.$slash.'bin'.$slash.'phinx ' . implode(' ', $options);
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

        return "php -S {$address}:{$port} -t . " . implode(' ', $options). base_path(). "public/index.php";
    }

    function phpUnitCommander($options = [])
    {
        $slash = DIRECTORY_SEPARATOR;
        return base_path().$slash.'vendor'.$slash.'bin'.$slash.'phpunit ' . implode(' ', $options);
    }
}