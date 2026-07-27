<?php

namespace Eyika\Atom\Framework\Foundation\Console\Commands;

use Eyika\Atom\Framework\Exceptions\Console\BaseConsoleException;
use Eyika\Atom\Framework\Foundation\Console\Command;
use Eyika\Atom\Framework\Foundation\Console\Concerns\RunsOnConsole;

class Test extends Command
{
    use RunsOnConsole;

    public string $signature = 'test';

    public function handle(): bool
    {
        try {
            array_unshift($this->arguments, 'tests');

            $code = $this->executeCommand($this->arguments, 'phpUnit');
        } catch (BaseConsoleException $e) {
            $this->error($e->getMessage(), $e->getTrace());
            return !(bool)($e->getCode());
        }
        return !(bool)$code;
    }
}
