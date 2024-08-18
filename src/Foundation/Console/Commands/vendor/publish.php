<?php

namespace Eyika\Atom\Framework\Foundation\Console\Commands\Vendor;

use Eyika\Atom\Framework\Exceptions\Console\BaseConsoleException;
use Eyika\Atom\Framework\Exceptions\NotImplementedException;
use Eyika\Atom\Framework\Foundation\Console\Command;

class Publish extends Command
{
    public function handle(array $arguments = []): int
    {
        try {
            throw new NotImplementedException('command is not yet implemented', 1);
        } catch (BaseConsoleException $e) {
            $this->error($e->getMessage());
            return $e->getCode();
        }
        return 0;
    }
}
