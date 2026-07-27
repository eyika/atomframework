<?php
namespace Eyika\Atom\Framework\Foundation\Console;

/**
 * Wrapper returned by the global dispatch() helper.
 * Auto-runs the job on destruct so fire-and-forget calls work,
 * while still allowing chained configuration (delay, onQueue, priority).
 */
class PendingDispatch
{
    protected object $job;
    protected bool $dispatched = false;

    public function __construct(object $job)
    {
        $this->job = $job;
        // Initialize the job's queue connection (matches existing $job->init() pattern)
        if (method_exists($job, 'init')) {
            $job->init();
        }
    }

    public function delay(int $delay): self
    {
        if (method_exists($this->job, 'delay')) {
            $this->job->delay($delay);
        }
        return $this;
    }

    public function onQueue(string $pipeline): self
    {
        if (method_exists($this->job, 'onQueue')) {
            $this->job->onQueue($pipeline);
        }
        return $this;
    }

    public function priority(int $prio): self
    {
        if (method_exists($this->job, 'priority')) {
            $this->job->priority($prio);
        }
        return $this;
    }

    public function onConnection($connection): self
    {
        if (method_exists($this->job, 'onConnection')) {
            $this->job->onConnection($connection);
        }
        return $this;
    }

    /**
     * Run the job. Called automatically on destruct if not already run.
     */
    public function run(): void
    {
        if ($this->dispatched) return;
        $this->dispatched = true;
        if (method_exists($this->job, 'run')) {
            $this->job->run();
        }
    }

    public function __destruct()
    {
        $this->run();
    }
}
