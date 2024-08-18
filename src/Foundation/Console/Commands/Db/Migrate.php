<?php

namespace Eyika\Atom\Framwork\Foundation\Console\Commands\Db;

use Eyika\Atom\Framework\Exceptions\Console\BaseConsoleException;
use Eyika\Atom\Framework\Foundation\Console\Concerns\RunsOnConsole;
use Eyika\Atom\Framwork\Foundation\Console\Command;

class Migrate extends Command
{
    use RunsOnConsole;

    public function handle(array $arguments = []): int
    {
        try {
            array_unshift($arguments, 'migrate');

            return $this->executeCommand($arguments);
        } catch (BaseConsoleException $e) {
            $this->error($e->getMessage());
            return $e->getCode();
        }
    }
}
