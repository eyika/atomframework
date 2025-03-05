<?php

namespace Eyika\Atom\Framework\Foundation\Console\Commands\Db;

use Eyika\Atom\Framework\Exceptions\Console\BaseConsoleException;
use Eyika\Atom\Framework\Foundation\Console\Concerns\RunsOnConsole;
use Eyika\Atom\Framework\Foundation\Console\Command;

class Rollback extends Command
{
    use RunsOnConsole;

    public string $signature = 'db:rollback';

    public function handle(): bool
    {
        try {
            array_unshift($this->arguments, 'rollback -t 0');

            $code = $this->executeCommand($this->arguments);
        } catch (BaseConsoleException $e) {
            $this->error($e->getMessage());
            return !(bool)($e->getCode());
        }
        return !(bool)$code;
    }
}
