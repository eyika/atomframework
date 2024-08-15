<?php

namespace Eyika\Atom\Framework\Foundation\Console;

use Eyika\Atom\Framework\Foundation\Contracts\ConsoleKernel;
use Eyika\Atom\Framwork\Foundation\Console\Command;
use Eyika\Atom\Framwork\Foundation\Console\Scheduler;

class ScheduleCommand extends Command
{
    protected $scheduler;
    protected $registry;

    public function __construct(Scheduler $scheduler, ConsoleKernel $registry)
    {
        $this->scheduler = $scheduler;
        $this->registry = $registry;
    }

    public function handle(array $arguments = [])
    {
        $this->scheduler->run($this->registry);
    }
}
