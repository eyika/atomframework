<?php

namespace Eyika\Atom\Framework\Foundation\Console\Commands\Db;

use Eyika\Atom\Framework\Exceptions\Console\BaseConsoleException;
use Eyika\Atom\Framework\Foundation\Console\Concerns\RunsOnConsole;
use Eyika\Atom\Framework\Foundation\Console\Command;

class Migrate extends Command
{
    use RunsOnConsole;

    public string $signature = 'migrate';

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
