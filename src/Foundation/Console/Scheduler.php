<?php

namespace Eyika\Atom\Framwork\Foundation\Console;

use Cron\CronExpression;
use Eyika\Atom\Framework\Foundation\Contracts\ConsoleKernel;

class Scheduler
{
    protected $tasks = [];
    protected $current_name = '';

    public function command(string $name, string $expression = null)
    {
        empty($expression) ?
            $this->current_name = $name :
            $this->tasks[] = [
                'command' => $name,
                'expression' => $expression
            ];

        return $this;
    }

    protected function expression(string $expression)
    {
        $this->tasks[] = [
            'command' => $this->current_name,
            'expression' => $expression
        ];
        $this->current_name = '';

        return $this;
    }

    public function hourly()
    {
        return $this->expression('@hourly');
    }

    public function daily()
    {
        return $this->expression('@daily');
    }

    public function midnight()
    {
        return $this->expression('@midnight');
    }

    public function weekly()
    {
        return $this->expression('@weekly');
    }

    public function monthly()
    {
        return $this->expression('@monthly');
    }

    public function yearly()
    {
        return $this->expression('@yearly');
    }

    public function annually()
    {
        return $this->yearly();
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