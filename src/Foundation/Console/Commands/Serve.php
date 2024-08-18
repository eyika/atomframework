<?php

namespace Eyika\Atom\Framework\Foundation\Console\Commands;

use Eyika\Atom\Framework\Exceptions\Console\BaseConsoleException;
use Eyika\Atom\Framework\Foundation\Console\Command;
use Eyika\Atom\Framework\Foundation\Console\Concerns\RunsOnConsole;

class Serve extends Command
{
    use RunsOnConsole;

    public string $signature = 'serve';

    public function handle(array $arguments = []): int
    {
        try {
            return $this->executeCommand($arguments, 'phpInbuiltServer');
        } catch (BaseConsoleException $e) {
            $this->error($e->getMessage());
            return $e->getCode();
        }
        return 0;
    }
}