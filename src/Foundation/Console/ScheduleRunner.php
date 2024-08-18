<?php

namespace Eyika\Atom\Framework\Foundation\Console;

use Exception;
use Eyika\Atom\Framework\Foundation\Contracts\ConsoleKernel;
use Eyika\Atom\Framework\Support\Facade\Facade;
use Eyika\Atom\Framework\Support\Facade\Scheduler;
use Eyika\Atom\Framwork\Foundation\Console\Command;

class ScheduleRunner extends Command
{
    public function handle(array $arguments = []): int
    {
        $app = Facade::getFacadeApplication();
        $kernel = $app->make(ConsoleKernel::class);
        try {
            Scheduler::run($kernel);
            return 0;
        } catch (Exception $e) {
            $this->error($e->getMessage());
            return 1;
        }
    }
}
