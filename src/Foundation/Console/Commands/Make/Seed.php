<?php

namespace Eyika\Atom\Framwork\Foundation\Console\Commands\Make;

use Eyika\Atom\Framework\Exceptions\Console\BaseConsoleException;
use Eyika\Atom\Framework\Exceptions\Console\InvalidInputException;
use Eyika\Atom\Framework\Foundation\Console\Concerns\RunsOnConsole;
use Eyika\Atom\Framwork\Foundation\Console\Command;

class Seed extends Command
{
    use RunsOnConsole;

    public function handle(array $arguments = []): int
    {
        try {
            if (empty($arguments[0] ?? '')) {
                throw new InvalidInputException('name of seed file is not specified', 1);
            }
    
            array_unshift($arguments, 'seed:create');
    
            return $this->executeCommand($arguments);
        } catch (BaseConsoleException $e) {
            $this->error($e->getMessage());
            return $e->getCode();
        }
    }
}
