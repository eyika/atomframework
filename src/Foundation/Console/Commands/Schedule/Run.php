<?php

namespace Eyika\Atom\Framework\Foundation\Console\Commands\Schedule;

use Exception;
use Eyika\Atom\Framework\Foundation\Console\Command;
use Eyika\Atom\Framework\Foundation\Contracts\ConsoleKernel;
use Eyika\Atom\Framework\Support\Facade\Facade;
use Eyika\Atom\Framework\Support\Facade\Scheduler;

class Run extends Command
{
    public string $signature = 'schedule:run';

    public function handle(array $arguments = []): bool
    {
        $app = Facade::getFacadeApplication();
        $kernel = $app->make(ConsoleKernel::class);
        try {
            Scheduler::run($kernel);
            return true;
        } catch (Exception $e) {
            $this->error($e->getMessage());
            return false;
        }
    }
}
