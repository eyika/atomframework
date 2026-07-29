<?php

namespace Eyika\Atom\Framework\Foundation\Console\Commands\Queue;

use Eyika\Atom\Framework\Exceptions\Console\BaseConsoleException;
use Eyika\Atom\Framework\Foundation\Console\Command;
use Eyika\Atom\Framework\Foundation\Console\JobRunner;

/**
 * queue:work — drain the job queue.
 *
 *   queue:work                          drain the default pipeline once and exit (cron-friendly)
 *   queue:work --daemon                 stay resident, sleeping when idle (needs a supervisor)
 *   queue:work --max-jobs=200           exit after 200 jobs (bounds a single run)
 *   queue:work --max-time=50            exit after ~50s (keep a run inside the scheduler tick)
 *   queue:work --once                   process a single job and exit
 *   queue:work --sleep=5                idle sleep seconds between polls (daemon mode)
 *   queue:work --pipeline=emails        watch a named pipeline instead of "default"
 *   queue:work --no-overlap-guard       allow concurrent workers on the same pipeline
 */
class work extends Command
{
    public string $signature = 'queue:work {--daemon} {--once} {--max-jobs=} {--max-time=} {--sleep=} {--pipeline=} {--no-overlap-guard}';

    public function handle(): bool
    {
        try {
            $runner = new JobRunner([
                'daemon'        => (bool) $this->option('daemon'),
                'once'          => (bool) $this->option('once'),
                'max_jobs'      => (int) $this->option('max-jobs', 0),
                'max_time'      => (int) $this->option('max-time', 0),
                'sleep'         => (int) $this->option('sleep', 3),
                'pipeline'      => (string) ($this->option('pipeline', 'default') ?: 'default'),
                'overlap_guard' => !$this->option('no-overlap-guard'),
            ]);
            $runner();
        } catch (BaseConsoleException $e) {
            $this->error($e->getMessage());
            return !(bool)($e->getCode());
        }
        return true;
    }
}
