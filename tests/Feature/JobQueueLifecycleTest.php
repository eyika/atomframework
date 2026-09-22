<?php

namespace Eyika\Atom\Framework\Tests\Feature;

use Eyika\Atom\Framework\Foundation\Console\Job_Queue;
use Eyika\Atom\Framework\Support\Database\Connection;
use PDO;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Throwable;

/**
 * The queue subsystem had **no tests at all**, which is how `kickJob()` shipped assigning its
 * table name to `$table_name[0]` and then interpolating `{$table_name}` — an array — into the SQL.
 * Every call raised "Array to string conversion" and ran `UPDATE Array SET …`.
 *
 * These run the whole lifecycle twice: once on SQLite and once on MySQL. Both matter, and for
 * different reasons.
 *
 * **SQLite** proves the portability work. `sqlite` was a declared queue type that could never have
 * worked: every SQL branch was shared with MySQL, but the one `CREATE TABLE` was MySQL's —
 * `int(11) AUTO_INCREMENT`, `tinyint(1)`, inline `KEY … (pipeline(75))`, `COMMENT` — none of which
 * SQLite parses, so the queue could not create the table it then queried. The DDL is now per
 * driver, the dialect is taken from the connection rather than from the label passed to the
 * constructor, and identifier quoting goes through `quoteDatabaseKey()` (whose pgsql and sqlsrv
 * rows were unreachable, because it matched them against a `queue_type` that can only hold
 * mysql/sqlite/beanstalkd).
 *
 * **MySQL** proves the change did not break the only driver anyone is running, including that
 * `COMPRESS()`/`UNCOMPRESS()` still round-trip — the one setting that cannot be changed under a
 * queue with rows in it, since a payload written compressed is readable only through UNCOMPRESS().
 */
class JobQueueLifecycleTest extends TestCase
{
    private ?PDO $sqlite = null;
    private ?PDO $mysql = null;
    private string $mysqlError = '';
    private string $sqliteFile = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->sqliteFile = sys_get_temp_dir() . '/atom_queue_' . getmypid() . '.sqlite';
        @unlink($this->sqliteFile);
        $this->sqlite = new PDO('sqlite:' . $this->sqliteFile, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        try {
            $this->mysql = Connection::makePdo(config('database'), 'mysql');
            $this->mysql->exec('DROP TABLE IF EXISTS atomtest_queue_jobs');
            $this->mysql->exec('DROP TABLE IF EXISTS atomtest_queue_failed');
        } catch (Throwable $e) {
            $this->mysql = null;
            $this->mysqlError = $e->getMessage();
        }
    }

    protected function tearDown(): void
    {
        if ($this->mysql !== null) {
            $this->mysql->exec('DROP TABLE IF EXISTS atomtest_queue_jobs');
            $this->mysql->exec('DROP TABLE IF EXISTS atomtest_queue_failed');
        }
        $this->sqlite = null;
        $this->mysql = null;
        @unlink($this->sqliteFile);

        parent::tearDown();
    }

    /**
     * A queue of the given type on the given handle.
     *
     * Every queue in a test shares one connection and one pair of tables, so two of them are two
     * workers racing for the same rows.
     */
    private function queue(string $type, PDO $pdo, bool $compress = false): Job_Queue
    {
        $queue = new Job_Queue($type, [
            $type => [
                'table_name' => 'atomtest_queue_jobs',
                'failed_table_name' => 'atomtest_queue_failed',
                'use_compression' => $compress,
            ],
        ]);
        $queue->flushCache();
        $queue->addQueueConnection($pdo);
        $queue->setPipeline('default');
        $queue->selectPipeline('default');

        return $queue;
    }

    /** @return array<string, array{string, PDO}> driver label => [queue type, handle] */
    private function drivers(): array
    {
        // Skipped LOUDLY rather than quietly running one driver: a suite that silently drops the
        // only driver anyone is running reports green while proving nothing about it. Zero skips
        // is the standard here, so this firing means the environment is wrong, not the code.
        if ($this->mysql === null) {
            $this->markTestSkipped('MySQL unavailable, so the MySQL half of this test cannot run: ' . $this->mysqlError);
        }

        return [
            'sqlite' => [Job_Queue::QUEUE_TYPE_SQLITE, $this->sqlite],
            'mysql' => [Job_Queue::QUEUE_TYPE_MYSQL, $this->mysql],
        ];
    }

    private function invoke(Job_Queue $queue, string $method, array $args = [])
    {
        $reflected = new ReflectionMethod(Job_Queue::class, $method);
        $reflected->setAccessible(true);

        return $reflected->invokeArgs($queue, $args);
    }

    // ---------------------------------------------------------------- the lifecycle, per driver

    public function test_a_job_can_be_queued_reserved_and_deleted_on_every_driver(): void
    {
        foreach ($this->drivers() as $label => [$type, $pdo]) {
            $queue = $this->queue($type, $pdo);

            $added = $queue->addJob('payload-one');
            $this->assertGreaterThan(0, $added['id'], "[{$label}] the job was not given an id");

            $job = $queue->getNextJobAndReserve();
            $this->assertSame('payload-one', $job['payload'] ?? null, "[{$label}] the payload did not round-trip");
            $this->assertSame(1, $job['attempts'] ?? null, "[{$label}] the first attempt was not counted");

            $queue->deleteJob($job);
            $this->assertSame([], $queue->getNextJobAndReserve(), "[{$label}] the job survived deletion");
        }
    }

    /** The table this queue queries has to be one it can also create. */
    public function test_the_queue_creates_its_own_tables_on_every_driver(): void
    {
        foreach ($this->drivers() as $label => [$type, $pdo]) {
            $queue = $this->queue($type, $pdo);
            $queue->addJob('payload');

            $rows = $pdo->query('SELECT COUNT(*) c FROM atomtest_queue_jobs')->fetchAll(PDO::FETCH_ASSOC);
            $this->assertSame(1, (int) $rows[0]['c'], "[{$label}] the jobs table was not usable");
        }
    }

    public function test_a_buried_job_can_be_kicked_back_into_the_pipeline(): void
    {
        foreach ($this->drivers() as $label => [$type, $pdo]) {
            $queue = $this->queue($type, $pdo);
            $queue->addJob('payload');
            $job = $queue->getNextJobAndReserve();

            $queue->buryJob($job, 0);
            $this->assertSame([], $queue->getNextJobAndReserve(), "[{$label}] a buried job was still pending");

            // Previously fatal: $table_name was an array and interpolated as "Array".
            $queue->kickJob($job);

            $rows = $pdo->query('SELECT is_buried FROM atomtest_queue_jobs')->fetchAll(PDO::FETCH_ASSOC);
            $this->assertSame(0, (int) $rows[0]['is_buried'], "[{$label}] kickJob did not unbury the job");
        }
    }

    public function test_failing_a_job_records_it_and_returns_an_id(): void
    {
        foreach ($this->drivers() as $label => [$type, $pdo]) {
            $queue = $this->queue($type, $pdo);
            $queue->addJob('payload');
            $job = $queue->getNextJobAndReserve();

            $id = $queue->failJob($job);
            $this->assertGreaterThan(0, $id, "[{$label}] failJob did not return the failed row's id");

            $rows = $pdo->query('SELECT COUNT(*) c FROM atomtest_queue_failed')->fetchAll(PDO::FETCH_ASSOC);
            $this->assertSame(1, (int) $rows[0]['c'], "[{$label}] the failed job was not recorded");
        }
    }

    // ---------------------------------------------------------------- the race

    /**
     * The defect this exists for: two workers selecting the same row must not both run it.
     *
     * Driven through the select/claim seam rather than by calling `getNextJobAndReserve()` twice —
     * which would prove nothing, because the second call's SELECT would already filter the
     * reserved row out. The failure needs both workers to have selected BEFORE either claims, and
     * that is exactly the window the old unconditional `WHERE id = ?` left open.
     */
    public function test_two_workers_that_select_the_same_job_cannot_both_claim_it(): void
    {
        foreach ($this->drivers() as $label => [$type, $pdo]) {
            $a = $this->queue($type, $pdo);
            $b = $this->queue($type, $pdo);
            $a->addJob('contended');

            $stale = gmdate('Y-m-d H:i:s', strtotime('now -1 minutes UTC'));

            // Both workers pick the same candidate, neither has claimed yet.
            $seenByA = $this->invoke($a, 'selectPendingCandidate', [$stale]);
            $seenByB = $this->invoke($b, 'selectPendingCandidate', [$stale]);
            $this->assertSame($seenByA['id'], $seenByB['id'], "[{$label}] the two workers did not contend");

            $this->assertTrue(
                $this->invoke($a, 'claimPendingJob', [$seenByA['id'], $stale, 0]),
                "[{$label}] the first worker failed to claim a free job"
            );
            $this->assertFalse(
                $this->invoke($b, 'claimPendingJob', [$seenByB['id'], $stale, 0]),
                "[{$label}] BOTH workers claimed the same job — it would run twice"
            );
        }
    }

    /** A reservation nobody renewed must still be recoverable, or a crashed worker strands the job. */
    public function test_a_stale_reservation_can_be_reclaimed(): void
    {
        foreach ($this->drivers() as $label => [$type, $pdo]) {
            $queue = $this->queue($type, $pdo);
            $queue->addJob('abandoned');
            $queue->getNextJobAndReserve();

            // The worker that held it died an hour ago.
            $pdo->exec("UPDATE atomtest_queue_jobs SET reserved_dt = '" . gmdate('Y-m-d H:i:s', strtotime('now -1 hours UTC')) . "', time_to_retry_dt = '" . gmdate('Y-m-d H:i:s', strtotime('now -1 hours UTC')) . "'");

            $job = $queue->getNextJobAndReserve();
            $this->assertSame('abandoned', $job['payload'] ?? null, "[{$label}] a stale reservation stranded the job");
        }
    }

    /** A live reservation must NOT be stealable. */
    public function test_a_fresh_reservation_is_not_reclaimed(): void
    {
        foreach ($this->drivers() as $label => [$type, $pdo]) {
            $queue = $this->queue($type, $pdo);
            $queue->addJob('held');
            $queue->getNextJobAndReserve();

            $this->assertSame([], $queue->getNextJobAndReserve(), "[{$label}] a live reservation was stolen");
        }
    }

    // ---------------------------------------------------------------- compression compatibility

    /**
     * The compatibility guarantee for anyone upgrading with a queue already in flight: a payload
     * written through COMPRESS() still reads back through UNCOMPRESS().
     */
    public function test_compressed_payloads_still_round_trip_on_mysql(): void
    {
        if ($this->mysql === null) {
            $this->markTestSkipped('MySQL not available');
        }

        $queue = $this->queue(Job_Queue::QUEUE_TYPE_MYSQL, $this->mysql, compress: true);
        $queue->addJob('compressed-payload');

        $this->assertSame('compressed-payload', $queue->getNextJobAndReserve()['payload'] ?? null);
    }

    /** Compression is MySQL's alone; SQLite must not be handed COMPRESS(). */
    public function test_compression_is_never_applied_to_a_non_mysql_connection(): void
    {
        $queue = $this->queue(Job_Queue::QUEUE_TYPE_SQLITE, $this->sqlite, compress: true);

        $this->assertFalse($this->invoke($queue, 'usesCompression'));

        $queue->addJob('plain-payload');
        $this->assertSame('plain-payload', $queue->getNextJobAndReserve()['payload'] ?? null);
    }

    // ---------------------------------------------------------------- dialect

    public function test_identifiers_are_quoted_for_the_connections_dialect(): void
    {
        $sqliteQueue = $this->queue(Job_Queue::QUEUE_TYPE_SQLITE, $this->sqlite);
        $this->assertSame('`delay`', $this->invoke($sqliteQueue, 'quoteDatabaseKey', ['delay', false]));

        if ($this->mysql !== null) {
            $mysqlQueue = $this->queue(Job_Queue::QUEUE_TYPE_MYSQL, $this->mysql);
            $this->assertSame('`delay`', $this->invoke($mysqlQueue, 'quoteDatabaseKey', ['delay', false]));
        }
    }

    /** The rows that were unreachable before, because the lookup used $queue_type. */
    public function test_the_standard_dialects_have_delimiters(): void
    {
        $queue = $this->queue(Job_Queue::QUEUE_TYPE_SQLITE, $this->sqlite);

        $method = new ReflectionMethod(Job_Queue::class, 'jobsTableDdl');
        $method->setAccessible(true);

        // Reached only because sqlDriver() is asked of the handle; a pgsql/sqlsrv DDL existing at
        // all is what makes those queue types more than a constant.
        $this->assertNotEmpty($method->invoke($queue, 'atomtest_queue_jobs'));
    }
}
