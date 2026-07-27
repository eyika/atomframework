<?php

namespace Eyika\Atom\Framework\Foundation\Console;

use Cron\CronExpression;
use Exception;
use Eyika\Atom\Framework\Exceptions\Console\BaseConsoleException;
use Eyika\Atom\Framework\Foundation\Console\Concerns\LogsMessages;
use Eyika\Atom\Framework\Foundation\Console\Contracts\QueueInterface;
use Eyika\Atom\Framework\Foundation\Console\Contracts\ShouldLogMessages;
use Eyika\Atom\Framework\Foundation\Contracts\ConsoleKernel;

class Scheduler implements ShouldLogMessages
{
    use LogsMessages;
    protected $tasks = [];
    protected $current_name = '';

    public function command(string|callable|QueueInterface $signature, array $arguements = [], string|null $expression = null): self
    {
        $this->tasks[] = [
            'command' => $signature,
            'arguements' => $arguements,
            'expression' => $expression
        ];

        return $this;
    }

    protected function expression(string $expression): self
    {
        if (!CronExpression::isValidExpression($expression)) {
            throw new Exception('expression string is not a valid cron expression');
        }
        if (empty($this->tasks)) {
            throw new BaseConsoleException('no command to attach the expression to');
        }

        $lastindex = count($this->tasks) - 1;
        $this->tasks[$lastindex]['expression'] = $expression;

        return $this;
    }

    /**
     * $key is like "--key= or -key= or --key or -key"
     * $value is any string
     */
    public function arguement(string $key, string $value): self
    {
        if (empty($this->tasks)) {
            throw new BaseConsoleException('no command to attach the arguement to');
        }

        $lastindex = count($this->tasks) - 1;
        $this->tasks[$lastindex]['arguements'][$key] = $value;

        return $this;
    }

    /**
     * arguements should be an associative array where
     * $key is like "--key= or -key= or --key or -key"
     * $value is any string
     */
    public function arguements(array $arguements): self
    {
        if (empty($this->tasks)) {
            throw new BaseConsoleException('no command to attach the arguements to');
        }

        $lastindex = count($this->tasks) - 1;

        foreach ($arguements as $key => $value) {
            $this->tasks[$lastindex]['arguements'][$key] = $value;
        }

        return $this;
    }

    public function everyMinute(): self
    {
        return $this->expression('* * * * *');
    }

    public function everyTwoMinutes(): self
    {
        return $this->expression('*/2 * * * *');
    }

    public function everyThreeMinutes(): self
    {
        return $this->expression('*/3 * * * *');
    }

    public function everyFiveMinutes(): self
    {
        return $this->expression('*/5 * * * *');
    }

    public function everyTenMinutes(): self
    {
        return $this->expression('*/10 * * * *');
    }

    public function everyFifteenMinutes(): self
    {
        return $this->expression('*/15 * * * *');
    }

    public function everyThirtyMinutes(): self
    {
        return $this->expression('0,30 * * * *');
    }

    public function hourly(): self
    {
        return $this->expression('@hourly');
    }

    public function daily(): self
    {
        return $this->expression('@daily');
    }

    public function midnight(): self
    {
        return $this->expression('@midnight');
    }

    public function weekly(): self
    {
        return $this->expression('@weekly');
    }

    public function monthly(): self
    {
        return $this->expression('@monthly');
    }

    public function yearly(): self
    {
        return $this->expression('@yearly');
    }

    public function annually(): self
    {
        return $this->yearly();
    }

    public function run(ConsoleKernel $registry): void
    {
        $now = new \DateTime();
        $registry->schedule();

        $ranCount = 0;
        foreach ($this->tasks as $task) {
            if (!($task['expression'] ?? null))
                continue;
            $expression = new CronExpression($task['expression']);
            if ($expression->isDue($now)) {
                $command = is_string($task['command']) ? $task['command'] : 'closure';
                $this->info("Running scheduled command: {$command}");
                $registry->run($task['command'], $task['arguements'], false);
                $ranCount++;
            }
        }

        if ($ranCount === 0) {
            $this->info('No scheduled commands are ready to run.');
        } else {
            $this->info("Ran {$ranCount} scheduled command(s).");
        }
    }
}
