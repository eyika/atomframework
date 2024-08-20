<?php

namespace Eyika\Atom\Framework\Foundation\Console\Commands\Db;

use Eyika\Atom\Framework\Exceptions\Console\BaseConsoleException;
use Eyika\Atom\Framework\Foundation\Console\Concerns\RunsOnConsole;
use Eyika\Atom\Framework\Foundation\Console\Command;

class Seed extends Command
{
    use RunsOnConsole;

    public string $signature = 'db:seed';

    public function handle(array $arguments = []): bool
    {
        try {
            array_unshift($arguments, 'seed:run');

            $code = $this->executeCommand($arguments);
        } catch (BaseConsoleException $e) {
            $this->error($e->getMessage());
            return !(bool)($e->getCode());
        }
        return !(bool)$code;
    }
}
