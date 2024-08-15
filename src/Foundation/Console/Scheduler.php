<?php

namespace Eyika\Atom\Framwork\Foundation\Console;

use Cron\CronExpression;
use Eyika\Atom\Framework\Foundation\Contracts\ConsoleKernel;

class Scheduler
{
    protected $tasks = [];

    public function command(string $name, string $expression)
    {
        $this->tasks[] = [
            'command' => $name,
            'expression' => $expression
        ];

        return $this;
    }

    public function run(ConsoleKernel $registry)
    {
        $now = new \DateTime();
        
        foreach ($this->tasks as $task) {
            $expression = new CronExpression($task['expression']);
            if ($expression->isDue($now)) {
                $registry->run($task['command']);
            }
        }
    }
}