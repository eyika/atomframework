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

    /**
     * Run the task once per day. Pass a 24-hour "HH:MM" (or "HH") to pick the time of
     * day — e.g. daily('03:30'); with no argument it runs at midnight.
     */
    public function daily(?string $time = null): self
    {
        return $time === null
            ? $this->expression('@daily')
            : $this->dailyAt($time);
    }

    /** Run the task once per day at the given 24-hour "HH:MM" time. */
    public function dailyAt(string $time): self
    {
        [$hour, $minute] = $this->parseTime($time);

        return $this->expression("{$minute} {$hour} * * *");
    }

    /** Alias of dailyAt(): pin the current task to a specific time of day. */
    public function at(string $time): self
    {
        return $this->dailyAt($time);
    }

    /** Run the task every hour, at the given minute past the hour. */
    public function hourlyAt(int $minute): self
    {
        return $this->expression(((int) $minute) . ' * * * *');
    }

    /**
     * Split a "HH:MM" (or bare "HH") 24-hour time into [hour, minute] ints.
     *
     * @return array{0:int,1:int}
     */
    protected function parseTime(string $time): array
    {
        $segments = explode(':', trim($time));
        $hour = (int) ($segments[0] ?? 0);
        $minute = (int) ($segments[1] ?? 0);

        if ($hour < 0 || $hour > 23 || $minute < 0 || $minute > 59) {
            throw new BaseConsoleException("Invalid schedule time [{$time}]; expected 24-hour HH:MM.");
        }

        return [$hour, $minute];
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

    /**
     * Prevent this task from starting if a previous run of it is still in flight — e.g. two
     * overlapping schedule:run ticks, or a long task that outlives its interval. Backed by an
     * flock the OS releases automatically if the runner dies, so it can't wedge shut.
     */
    public function withoutOverlapping(): self
    {
        if (empty($this->tasks)) {
            throw new BaseConsoleException('no command to apply withoutOverlapping to');
        }

        $this->tasks[count($this->tasks) - 1]['without_overlapping'] = true;

        return $this;
    }

    public function run(ConsoleKernel $registry): void
    {
        // Evaluate "is due" against the application's timezone, not whatever the CLI's
        // php.ini default happens to be — otherwise dailyAt('05:00') fires at 05:00
        // server-local instead of the intended app time.
        $now = new \DateTime('now', new \DateTimeZone($this->timezone()));
        $registry->schedule();

        $ranCount = 0;
        foreach ($this->tasks as $index => $task) {
            if (!($task['expression'] ?? null))
                continue;
            $expression = new CronExpression($task['expression']);
            if (!$expression->isDue($now)) {
                continue;
            }

            $command = is_string($task['command']) ? $task['command'] : 'closure';

            // withoutOverlapping(): if a previous run of this same task is still in flight
            // (overlapping schedule:run ticks), skip this one instead of double-firing.
            $lock = null;
            if (!empty($task['without_overlapping'])) {
                $lock = $this->acquireTaskLock($task, $index);
                if ($lock === null) {
                    $this->info("Skipping (already running): {$command}");
                    continue;
                }
            }

            try {
                $this->info("Running scheduled command: {$command}");
                $registry->run($task['command'], $task['arguements'], false);
                $ranCount++;
            } finally {
                $this->releaseLock($lock);
            }
        }

        if ($ranCount === 0) {
            $this->info('No scheduled commands are ready to run.');
        } else {
            $this->info("Ran {$ranCount} scheduled command(s).");
        }
    }

    /** The application timezone cron expressions are matched against (defaults to UTC). */
    protected function timezone(): string
    {
        if (function_exists('config')) {
            try {
                $tz = config('app.timezone', 'UTC');
            } catch (\Throwable) {
                $tz = 'UTC';
            }
            if (is_string($tz) && $tz !== '') {
                return $tz;
            }
        }

        return 'UTC';
    }

    /**
     * Take an exclusive, non-blocking flock keyed by the task, or null if another run holds it.
     * The handle is returned so the caller can release it once the task finishes.
     *
     * @param array<string,mixed> $task
     * @return resource|null
     */
    protected function acquireTaskLock(array $task, int $index)
    {
        $key = is_string($task['command']) ? $task['command'] : "closure_{$index}";
        $dir = function_exists('storage_path') ? storage_path('framework') : sys_get_temp_dir();
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $file = $dir . '/schedule-' . md5($key) . '.lock';

        $handle = @fopen($file, 'c');
        if ($handle === false) {
            return $handle; // can't lock — fail open, run the task
        }
        if (!flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);
            return null; // still running
        }

        return $handle;
    }

    /** Release a lock taken by acquireTaskLock(). Accepts null/false for the no-lock path. */
    protected function releaseLock($handle): void
    {
        if (is_resource($handle)) {
            @flock($handle, LOCK_UN);
            @fclose($handle);
        }
    }
}
