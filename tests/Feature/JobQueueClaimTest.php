<?php

namespace Eyika\Atom\Framework\Tests\Feature;

use Eyika\Atom\Framework\Foundation\Console\Job_Queue;
use PDO;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;

/**
 * Reported by a downstream consumer after auditing the queue against Solid Queue and Shopify's
 * inventory-reservation work, both of which solve this problem with `SELECT … FOR UPDATE SKIP
 * LOCKED`.
 *
 * The claim was a two-statement optimistic compare-and-swap in autocommit, with `rowCount() === 1`
 * as the entire concurrency control. Correct, but with three consequences:
 *
 * 1. **O(N) wasted reads per claim.** The SELECT takes no locks, so all N workers read the same
 *    `LIMIT 1` row and N−1 lose the race and re-read.
 * 2. **False idle, which is a starvation mode.** A worker that lost every race returned the same
 *    empty array as one with nothing to do, so the runner slept *with work pending* — and the
 *    busier the queue, the more often that happened.
 * 3. **A hardcoded 60-second lease, never renewed.** A `handle()` running longer than that became
 *    eligible to be claimed again while still executing, which made "finish inside a minute" an
 *    undocumented correctness requirement. It had already failed once in the field: BUG-67's
 *    timezone displacement put that cutoff ~59 minutes in the future.
 *
 * `ORDER BY priority ASC` also had no tiebreaker while every job defaults to `priority = 1024`, so
 * the queue was not FIFO and had no defined order at all among equal-priority jobs.
 *
 * **What is NOT changed, deliberately:** the compare-and-swap path stays, because MySQL below 8.0
 * and MariaDB below 10.6 have no SKIP LOCKED and removing it would break those deployments
 * silently, at the moment a job is claimed. `is_reserved`/`reserved_dt` keep their meaning, because
 * the `jobs` table is framework-created and under no project's migration control.
 *
 * **On proving it:** SQLite compiles every lock away to an empty string, so these cover the parts
 * SQLite can actually decide — ordering, the lease, contention reporting, and the CAS path — while
 * the emitted lock text is asserted in `SkipLockedTest`. A true N-worker concurrency harness needs
 * a real server and is noted in the handoff as the remaining gap.
 */
class JobQueueClaimTest extends TestCase
{
    private ?PDO $sqlite = null;
    private string $sqliteFile = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->sqliteFile = sys_get_temp_dir() . '/atom_queue_claim_' . getmypid() . '.sqlite';
        @unlink($this->sqliteFile);
        $this->sqlite = new PDO('sqlite:' . $this->sqliteFile, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }

    protected function tearDown(): void
    {
        $this->sqlite = null;
        @unlink($this->sqliteFile);

        parent::tearDown();
    }

    private function queue(array $options = []): Job_Queue
    {
        $queue = new Job_Queue(Job_Queue::QUEUE_TYPE_SQLITE, [
            Job_Queue::QUEUE_TYPE_SQLITE => array_merge([
                'table_name' => 'atomtest_claim_jobs',
                'failed_table_name' => 'atomtest_claim_failed',
                'use_compression' => false,
            ], $options),
        ]);
        $queue->flushCache();
        $queue->addQueueConnection($this->sqlite);
        $queue->setPipeline('default');
        $queue->selectPipeline('default');

        return $queue;
    }

    private function invoke(Job_Queue $queue, string $method, array $args = [])
    {
        $method = new ReflectionMethod(Job_Queue::class, $method);
        $method->setAccessible(true);

        return $method->invokeArgs($queue, $args);
    }

    // ---------------------------------------------------------------- ordering

    /**
     * Every job defaults to `priority = 1024`, so without a tiebreaker the queue had no defined
     * order whatsoever among ordinary jobs — "FIFO" was an accident of whatever the planner
     * returned.
     */
    public function test_equal_priority_jobs_come_back_in_insertion_order(): void
    {
        $queue = $this->queue();

        foreach (['first', 'second', 'third'] as $payload) {
            $queue->addJob($payload);
        }

        $seen = [];
        for ($i = 0; $i < 3; $i++) {
            $job = $queue->getNextJobAndReserve();
            $seen[] = $job['payload'];
            $queue->deleteJob($job);
        }

        $this->assertSame(['first', 'second', 'third'], $seen);
    }

    /** Priority still wins over insertion order — the tiebreaker only breaks ties. */
    public function test_priority_still_outranks_insertion_order(): void
    {
        $queue = $this->queue();

        $queue->addJob('ordinary');
        $queue->addJob('urgent', 0, 1);

        $this->assertSame('urgent', $queue->getNextJobAndReserve()['payload']);
    }

    public function test_the_claim_query_orders_by_id_as_a_tiebreaker(): void
    {
        $queue = $this->queue();
        $queue->addJob('payload');

        // Assert the ordering is in the SQL, not merely observed — an index change or a planner
        // difference could otherwise make an unordered query look ordered on a small table.
        $source = file_get_contents(dirname(__DIR__, 2) . '/src/Foundation/Console/Job_Queue.php');

        $this->assertStringNotContainsString(
            'ORDER BY priority ASC LIMIT',
            $source,
            'a claim query still orders by priority alone, which is undefined among equal priorities'
        );
        $this->assertStringContainsString('ORDER BY priority ASC, id ASC', $source);
    }

    // ---------------------------------------------------------------- the lease

    public function test_the_reservation_lease_defaults_to_a_minute(): void
    {
        $this->assertSame(60, $this->queue()->reservationTimeout());
    }

    public function test_the_reservation_lease_is_configurable(): void
    {
        $this->assertSame(900, $this->queue(['reservation_timeout' => 900])->reservationTimeout());
    }

    /** A zero or negative lease would make every reservation instantly stale. */
    public function test_a_nonsense_lease_is_clamped_rather_than_obeyed(): void
    {
        $this->assertGreaterThan(0, $this->queue(['reservation_timeout' => 0])->reservationTimeout());
        $this->assertGreaterThan(0, $this->queue(['reservation_timeout' => -5])->reservationTimeout());
    }

    /**
     * The lease is honoured rather than merely stored: with a long lease a reservation holds, and
     * the previously-hardcoded 60 seconds no longer decides it.
     */
    public function test_a_long_lease_keeps_a_reservation_held(): void
    {
        $queue = $this->queue(['reservation_timeout' => 3600]);
        $queue->addJob('held');
        $queue->getNextJobAndReserve();

        // Reserved half an hour ago — stale under the old hardcoded minute, live under this lease.
        $half_hour_ago = gmdate('Y-m-d H:i:s', time() - 1800);
        $this->sqlite->exec("UPDATE atomtest_claim_jobs SET reserved_dt = '{$half_hour_ago}', time_to_retry_dt = '{$half_hour_ago}'");

        $this->assertSame([], $queue->getNextJobAndReserve(), 'the configured lease was ignored');
    }

    public function test_a_short_lease_releases_a_reservation_sooner(): void
    {
        $queue = $this->queue(['reservation_timeout' => 5]);
        $queue->addJob('released');
        $queue->getNextJobAndReserve();

        $ten_seconds_ago = gmdate('Y-m-d H:i:s', time() - 10);
        $this->sqlite->exec("UPDATE atomtest_claim_jobs SET reserved_dt = '{$ten_seconds_ago}', time_to_retry_dt = '{$ten_seconds_ago}'");

        $this->assertSame('released', $queue->getNextJobAndReserve()['payload'] ?? null);
    }

    /** A long-running handler must be able to keep its own lease alive. */
    public function test_touching_a_job_extends_its_reservation(): void
    {
        $queue = $this->queue(['reservation_timeout' => 5]);
        $queue->addJob('slow');
        $job = $queue->getNextJobAndReserve();

        $ten_seconds_ago = gmdate('Y-m-d H:i:s', time() - 10);
        $this->sqlite->exec("UPDATE atomtest_claim_jobs SET reserved_dt = '{$ten_seconds_ago}'");

        $this->assertTrue($queue->touchJob($job), 'touchJob() did not update the reservation');
        $this->assertSame([], $queue->getNextJobAndReserve(), 'a touched reservation was still taken');
    }

    // ---------------------------------------------------------------- contention vs idle

    /** An empty pipeline is idle, and must still report itself that way. */
    public function test_an_empty_pipeline_is_not_reported_as_contended(): void
    {
        $queue = $this->queue();

        $this->assertSame([], $queue->getNextJobAndReserve());
        $this->assertFalse($queue->sawContention());
    }

    public function test_a_successful_claim_is_not_reported_as_contended(): void
    {
        $queue = $this->queue();
        $queue->addJob('payload');

        $queue->getNextJobAndReserve();

        $this->assertFalse($queue->sawContention());
    }

    /**
     * The starvation case: a worker that keeps finding candidates but never wins one is looking at
     * a BUSY queue, and must not be mistaken for a worker with nothing to do.
     */
    public function test_losing_every_race_reports_contention_rather_than_idleness(): void
    {
        $queue = $this->queue();
        $queue->addJob('contended');

        // Stand in for the winner: every claim this worker attempts is taken first.
        $losing = new class (Job_Queue::QUEUE_TYPE_SQLITE, [
            Job_Queue::QUEUE_TYPE_SQLITE => [
                'table_name' => 'atomtest_claim_jobs',
                'failed_table_name' => 'atomtest_claim_failed',
                'use_compression' => false,
            ],
        ]) extends Job_Queue {
            protected function claimPendingJob(int $id, string $stale_before, int $delay): bool
            {
                return false;
            }
        };
        $losing->addQueueConnection($this->sqlite);
        $losing->setPipeline('default');
        $losing->selectPipeline('default');

        $this->assertSame([], $losing->getNextJobAndReserve(), 'the losing worker somehow claimed a job');
        $this->assertTrue(
            $losing->sawContention(),
            'a worker that lost every race reported an idle queue — the runner would sleep on a backlog'
        );
    }

    // ---------------------------------------------------------------- path selection

    /** SQLite has no SKIP LOCKED, so it must take the compare-and-swap path and keep working. */
    public function test_sqlite_takes_the_compare_and_swap_path(): void
    {
        $queue = $this->queue();

        $this->assertFalse($queue->supportsSkipLocked());

        $queue->addJob('payload');
        $this->assertSame('payload', $queue->getNextJobAndReserve()['payload'] ?? null);
    }

    /** The capability answer is memoised — it costs a round trip on a real server. */
    public function test_the_capability_answer_is_cached(): void
    {
        $queue = $this->queue();
        $queue->supportsSkipLocked();

        $property = new ReflectionProperty(Job_Queue::class, 'skipLockedSupport');
        $property->setAccessible(true);

        $this->assertNotNull($property->getValue($queue));
    }

    // ---------------------------------------------------------------- against a real server

    /**
     * The locked path and `touchJob()` can only be judged on a server that really locks and that
     * counts rows CHANGED rather than matched. SQLite does neither, so it cannot see either bug.
     */
    private function serverQueue(): array
    {
        try {
            $pdo = \Eyika\Atom\Framework\Support\Database\Connection::makePdo(config('database'), 'mysql');
        } catch (\Throwable $e) {
            $this->markTestSkipped('No MySQL/MariaDB available: ' . $e->getMessage());
        }

        $pdo->exec('DROP TABLE IF EXISTS atomtest_claim_jobs');
        $pdo->exec('DROP TABLE IF EXISTS atomtest_claim_failed');

        $queue = new Job_Queue(Job_Queue::QUEUE_TYPE_MYSQL, [
            Job_Queue::QUEUE_TYPE_MYSQL => [
                'table_name' => 'atomtest_claim_jobs',
                'failed_table_name' => 'atomtest_claim_failed',
                'use_compression' => true,
            ],
        ]);
        $queue->flushCache();
        $queue->addQueueConnection($pdo);
        $queue->setPipeline('default');
        $queue->selectPipeline('default');

        return [$queue, $pdo];
    }

    /**
     * A job touches its own lease promptly, so the touch usually lands in the SAME SECOND the
     * reservation was written. MySQL and MariaDB count rows *changed*, so rewriting an identical
     * timestamp changes nothing and reports zero — which read as "you no longer hold this job".
     * SQLite counts matched rows and returns 1, so a SQLite-only test calls this passing.
     */
    public function test_touching_within_the_same_second_still_reports_success(): void
    {
        [$queue, $pdo] = $this->serverQueue();

        try {
            $queue->addJob('slow');
            $job = $queue->getNextJobAndReserve();

            $this->assertNotEmpty($job, 'the job was not claimed');
            $this->assertTrue(
                $queue->touchJob($job),
                'a touch in the same second as the reservation reported failure'
            );
        } finally {
            $pdo->exec('DROP TABLE IF EXISTS atomtest_claim_jobs');
            $pdo->exec('DROP TABLE IF EXISTS atomtest_claim_failed');
        }
    }

    /** …but a job we no longer hold must still say so. */
    public function test_touching_a_job_we_no_longer_hold_fails(): void
    {
        [$queue, $pdo] = $this->serverQueue();

        try {
            $queue->addJob('gone');
            $job = $queue->getNextJobAndReserve();
            $queue->deleteJob($job);

            $this->assertFalse($queue->touchJob($job));
        } finally {
            $pdo->exec('DROP TABLE IF EXISTS atomtest_claim_jobs');
            $pdo->exec('DROP TABLE IF EXISTS atomtest_claim_failed');
        }
    }

    /**
     * On a capable server the claim must actually take the locked path — otherwise every
     * SKIP LOCKED assertion here is about code nothing runs.
     */
    public function test_a_capable_server_claims_through_the_row_lock(): void
    {
        [$queue, $pdo] = $this->serverQueue();

        try {
            if (!$queue->supportsSkipLocked()) {
                $this->markTestSkipped('This server has no SKIP LOCKED, so the CAS path is correct here.');
            }

            $queue->addJob('first');
            $queue->addJob('second');

            $this->assertSame('first', $queue->getNextJobAndReserve()['payload'] ?? null);
            $this->assertSame('second', $queue->getNextJobAndReserve()['payload'] ?? null);
            $this->assertSame([], $queue->getNextJobAndReserve(), 'a held job was handed out again');
        } finally {
            $pdo->exec('DROP TABLE IF EXISTS atomtest_claim_jobs');
            $pdo->exec('DROP TABLE IF EXISTS atomtest_claim_failed');
        }
    }

    /** Both claim paths must exist; deleting the fallback breaks every pre-8.0 deployment. */
    public function test_both_claim_paths_are_present(): void
    {
        $this->assertTrue(method_exists(Job_Queue::class, 'claimWithRowLock'));
        $this->assertTrue(method_exists(Job_Queue::class, 'claimByCompareAndSwap'));
    }
}
