<?php
namespace Eyika\Atom\Framework\Foundation\Console;

use Eyika\Atom\Framework\Foundation\Console\Contracts\QueueInterface;
use Eyika\Atom\Framework\Support\SignedPayload;
use PDO;
use Throwable;

/**
 * Drains the MySQL-backed job queue.
 *
 * Two run models, selected by options:
 *   - periodic (default): reserve and run due jobs until the pipeline is empty, then return.
 *     Invoke it on a schedule, e.g. Scheduler::command('queue:work')->everyMinute().
 *   - daemon (--daemon): stay resident, sleeping when idle, until a run-cap is hit. Run it
 *     under a process supervisor on hosts that permit long-lived processes.
 *
 * Run-caps (--max-jobs / --max-time / --once) bound a single invocation so a backlog can't make
 * one run overrun the next scheduler tick. The flock overlap guard (on by default) keeps two
 * workers off the same pipeline at once — the queue-worker equivalent of the scheduler's
 * withoutOverlapping(). A job that throws is buried for a later retry and logged; it never kills
 * the worker, so the rest of the batch still drains.
 */
class JobRunner
{
    /** @var array<string, mixed> */
    private array $options;

    /** @var resource|null Open handle whose flock is the overlap guard. */
    private $lockHandle = null;

    /**
     * @param array<string, mixed> $options daemon, once, max_jobs, max_time, sleep, pipeline, overlap_guard
     */
    public function __construct(array $options = [])
    {
        $this->options = array_merge([
            'daemon'        => false,
            'once'          => false,
            'max_jobs'      => 0,        // 0 = unlimited
            'max_time'      => 0,        // seconds; 0 = unlimited
            'sleep'         => 3,        // idle sleep (seconds), daemon mode only
            'pipeline'      => 'default',
            'overlap_guard' => true,
        ], $options);
    }

    public function __invoke(): void
    {
        $pipeline = (string) ($this->options['pipeline'] ?: 'default');

        if ($this->options['overlap_guard'] && !$this->acquireLock($pipeline)) {
            info("queue:work [{$pipeline}] skipped: another worker already holds the lock.");
            return;
        }

        try {
            $queue = $this->makeQueue($pipeline);
            $start = time();
            $processed = 0;

            while (true) {
                $job = $this->reserveNext($queue);

                if ($job !== null) {
                    $this->process($queue, $job);
                    $processed++;

                    if ($this->options['once']) {
                        break;
                    }
                    if ($this->maxJobs() > 0 && $processed >= $this->maxJobs()) {
                        break;
                    }
                } elseif (!$this->options['daemon']) {
                    break; // periodic mode: nothing due, drain-and-exit
                } else {
                    if ($this->maxTimeReached($start)) {
                        break;
                    }
                    sleep(max(1, (int) $this->options['sleep']));
                }

                if ($this->maxTimeReached($start)) {
                    break;
                }
            }
        } finally {
            $this->releaseLock();
        }
    }

    private function makeQueue(string $pipeline): Job_Queue
    {
        //TODO: db abstraction so we can be driver-agnostic
        $dbtype = config('database.connections.mysql.driver');
        $dbhost = config('database.connections.mysql.host');
        $dbname = config('database.connections.mysql.database');
        $dbuser = config('database.connections.mysql.username');
        $dbpass = config('database.connections.mysql.password');

        $queue = new Job_Queue(Job_Queue::QUEUE_TYPE_MYSQL, [
            $dbtype => [
                'table_name'      => 'jobs',
                'use_compression' => true,
            ],
        ]);
        $queue->addQueueConnection(new PDO("$dbtype:dbname=$dbname;host=$dbhost", $dbuser, $dbpass));
        $queue->watchPipeline($pipeline);

        return $queue;
    }

    /** Reserve the next pending job, falling back to a due buried job. Null when the pipeline is idle. */
    private function reserveNext(Job_Queue $queue): ?array
    {
        if (!empty($job = $queue->getNextJobAndReserve())) {
            return $job;
        }
        if (!empty($job = $queue->getNextBuriedJob())) {
            return $job;
        }
        return null;
    }

    private function process(Job_Queue $queue, array $job): void
    {
        $job_obj = null;
        try {
            $job_obj = SignedPayload::verify($job['payload']);

            if ($job_obj instanceof QueueInterface) {
                $job_obj->setJob($job);
                $job_obj->setQueue($queue);
                $job_obj->handle();
            } else {
                // A reserved-but-unrunnable payload: leave it reserved rather than looping on it.
                info('queue: reserved payload is not a QueueInterface job; skipping.');
            }
        } catch (Throwable $e) {
            // Isolate the failure — bury for a later retry and keep the worker alive so the
            // rest of the batch still drains. The app decides when to fail() after N attempts.
            $delay = ($job_obj instanceof QueueInterface) ? $job_obj->getDelay() : 60;
            try {
                $queue->buryJob($job, $delay);
            } catch (Throwable) {
                // best-effort: never let a bury failure escape and kill the loop
            }
            logger()->error('queue: job failed, buried for retry: ' . $e->getMessage(), [
                'exception' => get_class($e),
                'job_id'    => $job['id'] ?? null,
            ]);
        }
    }

    private function maxJobs(): int
    {
        return (int) $this->options['max_jobs'];
    }

    private function maxTimeReached(int $start): bool
    {
        $max = (int) $this->options['max_time'];
        return $max > 0 && (time() - $start) >= $max;
    }

    // ---- overlap guard (flock; the OS releases it automatically if the worker dies) ----

    private function acquireLock(string $pipeline): bool
    {
        $dir = storage_path('framework');
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $safe = preg_replace('/[^A-Za-z0-9_.-]/', '_', $pipeline);
        $handle = @fopen($dir . '/queue-work-' . $safe . '.lock', 'c');

        if ($handle === false) {
            return true; // can't create a lock file — fail open rather than block the queue
        }
        if (!flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);
            return false; // another worker holds it
        }

        $this->lockHandle = $handle;
        return true;
    }

    private function releaseLock(): void
    {
        if (is_resource($this->lockHandle)) {
            @flock($this->lockHandle, LOCK_UN);
            @fclose($this->lockHandle);
            $this->lockHandle = null;
        }
    }
}
