<?php
namespace Eyika\Atom\Framework\Foundation\Console\Concerns;

trait RunsOnConsole
{
    // Function to execute the command and display output in real-time
    function executeCommand($options = [])
    {
        $command = $this->phinxCommander($options);

        $process = proc_open($command, [
            1 => ['pipe', 'w'], // stdout
            2 => ['pipe', 'w'], // stderr
        ], $pipes);

        if (is_resource($process)) {
            while ($line = fgets($pipes[1])) {
                echo $line;
            }

            while ($line = fgets($pipes[2])) {
                echo $line;
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

        return base_path().$slash.'vendor'.$slash.'bin'.$slash.'phinx ' . implode(' ', $options);
    }
}