<?php

namespace Eyika\Atom\Framework\Foundation\Console\Commands\Make;

use Eyika\Atom\Framework\Exceptions\Console\BaseConsoleException;
use Eyika\Atom\Framework\Exceptions\Console\InvalidInputException;
use Eyika\Atom\Framework\Foundation\Console\Concerns\RunsOnConsole;
use Eyika\Atom\Framework\Foundation\Console\Command;

class Seed extends Command
{
    public string $signature = 'make:seed';

    use RunsOnConsole;

    public function handle(array $arguments = []): bool
    {
        try {
            if (empty($arguments[0] ?? '')) {
                throw new InvalidInputException('name of seed file is not specified', 1);
            }
    
            array_unshift($arguments, 'seed:create');
    
            $code = $this->executeCommand($arguments);
        } catch (BaseConsoleException $e) {
            $this->error($e->getMessage(), $e->getTrace());
            return !(bool)($e->getCode());
        }

        return !(bool)$code;
    }
}
