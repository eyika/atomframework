<?php

namespace Eyika\Atom\Framework\Tests\Feature;

use Eyika\Atom\Framework\Foundation\Console\Job_Queue;
use Eyika\Atom\Framework\Support\Database\Connection;
use PDO;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Throwable;

/**
 * Two defects found by a downstream consumer while verifying the previous queue release, both
 * about the environment the queue runs in rather than the SQL it writes.
 *
 * **The clock.** Every stored datetime was built as
 * `gmdate('Y-m-d H:i:s', strtotime('now +N seconds UTC'))`. `strtotime()` reads "now" on the LOCAL
 * clock, and the trailing "UTC" then relabels that wall-clock reading as UTC — so the result is
 * displaced by the offset rather than converted by it. Measured here: a 60-second delay becomes
 * 3660 at `Africa/Lagos` and **-14340** at `America/New_York`, where a job is due nearly four hours
 * before it was queued.
 *
 * The reporter framed this as delayed jobs running an hour late, which is the visible half. The
 * reservation guard was the worse casualty and is what these tests mostly cover: its cutoff is
 * "now minus one minute", which east of Greenwich computed to nearly an hour in the FUTURE — so
 * `reserved_dt <= cutoff` held for a reservation made a moment ago, and a job another worker had
 * just taken looked abandoned. That silently undoes the double-run fix shipped in the same
 * release, for every deployment east of UTC. West of it the cutoff sits hours in the past instead,
 * so a reservation never expires and a crashed worker strands its job permanently.
 *
 * It was invisible because the test process and CI both run UTC, which is the one timezone where
 * the arithmetic is accidentally right. These tests therefore run the clock cases under a real
 * non-UTC timezone.
 *
 * **The schema scope.** `sqlTableExists()` asked `information_schema.tables` for a table name with
 * no database qualifier, and that view spans every schema on the server — so a `jobs` table in a
 * sibling database answered for this one, table creation was skipped, and the first real query
 * failed against a table that did not exist. A test database beside a development one is the
 * ordinary local arrangement. This was a regression introduced by the previous release, which
 * replaced a correctly-scoped `SHOW TABLES LIKE` to fix its LIKE-pattern behaviour and lost the
 * scope in the process.
 */
class JobQueueEnvironmentTest extends TestCase
{
    private ?PDO $mysql = null;
    private string $mysqlError = '';
    private string $sqliteFile = '';
    private ?PDO $sqlite = null;
    private string $originalTimezone = 'UTC';

    private const OTHER_SCHEMA = 'atomtest_other_schema';

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalTimezone = date_default_timezone_get();

        $this->sqliteFile = sys_get_temp_dir() . '/atom_queue_env_' . getmypid() . '.sqlite';
        @unlink($this->sqliteFile);
        $this->sqlite = new PDO('sqlite:' . $this->sqliteFile, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        try {
            $this->mysql = Connection::makePdo(config('database'), 'mysql');
            $this->mysql->exec('DROP TABLE IF EXISTS atomtest_env_jobs');
            $this->mysql->exec('DROP TABLE IF EXISTS atomtest_env_failed');
        } catch (Throwable $e) {
            $this->mysql = null;
            $this->mysqlError = $e->getMessage();
        }
    }

    protected function tearDown(): void
    {
        date_default_timezone_set($this->originalTimezone);

        if ($this->mysql !== null) {
            $this->mysql->exec('DROP TABLE IF EXISTS atomtest_env_jobs');
            $this->mysql->exec('DROP TABLE IF EXISTS atomtest_env_failed');
            $this->mysql->exec('DROP DATABASE IF EXISTS ' . self::OTHER_SCHEMA);
        }

        $this->sqlite = null;
        $this->mysql = null;
        @unlink($this->sqliteFile);

        parent::tearDown();
    }

    private function queue(string $type, PDO $pdo, string $table = 'atomtest_env_jobs'): Job_Queue
    {
        $queue = new Job_Queue($type, [
            $type => [
                'table_name' => $table,
                'failed_table_name' => 'atomtest_env_failed',
                'use_compression' => false,
            ],
        ]);
        $queue->flushCache();
        $queue->addQueueConnection($pdo);
        $queue->setPipeline('default');
        $queue->selectPipeline('default');

        return $queue;
    }

    private function invoke(Job_Queue $queue, string $method, array $args = [])
    {
        $reflected = new ReflectionMethod(Job_Queue::class, $method);
        $reflected->setAccessible(true);

        return $reflected->invokeArgs($queue, $args);
    }

    private function requireMysql(): PDO
    {
        if ($this->mysql === null) {
            $this->markTestSkipped('MySQL unavailable: ' . $this->mysqlError);
        }

        return $this->mysql;
    }

    /** Timezones on both sides of Greenwich, because the two directions fail differently. */
    private function timezones(): array
    {
        return ['UTC', 'Africa/Lagos', 'America/New_York', 'Asia/Kathmandu'];
    }

    // ---------------------------------------------------------------- the clock

    /** The reported symptom: a delayed job must become due exactly $delay seconds after it is added. */
    public function test_a_delayed_job_is_due_after_exactly_the_delay(): void
    {
        foreach ($this->timezones() as $tz) {
            date_default_timezone_set($tz);

            $queue = $this->queue(Job_Queue::QUEUE_TYPE_SQLITE, $this->sqlite);
            $this->sqlite->exec('DROP TABLE IF EXISTS atomtest_env_jobs');
            $queue->flushCache();

            $queue->addJob('delayed', 60);

            $row = $this->sqlite->query('SELECT added_dt, send_dt FROM atomtest_env_jobs')->fetchAll(PDO::FETCH_ASSOC)[0];
            $gap = strtotime($row['send_dt'] . ' UTC') - strtotime($row['added_dt'] . ' UTC');

            $this->assertSame(60, $gap, "[{$tz}] a 60-second delay produced a gap of {$gap}s");
        }
    }

    /** added_dt must be the real UTC instant, not a local reading wearing a UTC label. */
    public function test_a_queued_job_is_stamped_in_real_utc(): void
    {
        foreach ($this->timezones() as $tz) {
            date_default_timezone_set($tz);

            $queue = $this->queue(Job_Queue::QUEUE_TYPE_SQLITE, $this->sqlite);
            $this->sqlite->exec('DROP TABLE IF EXISTS atomtest_env_jobs');
            $queue->flushCache();

            $before = time();
            $queue->addJob('stamped');
            $after = time();

            $added = strtotime(
                $this->sqlite->query('SELECT added_dt FROM atomtest_env_jobs')->fetchAll(PDO::FETCH_ASSOC)[0]['added_dt'] . ' UTC'
            );

            $this->assertGreaterThanOrEqual($before, $added, "[{$tz}] added_dt is behind the real clock");
            $this->assertLessThanOrEqual($after, $added, "[{$tz}] added_dt is ahead of the real clock");
        }
    }

    /**
     * The consequence that matters most, and the one the report did not mention.
     *
     * East of Greenwich the staleness cutoff landed in the future, so a job another worker had
     * reserved a moment ago read as abandoned — which defeats the double-run guard shipped in the
     * previous release precisely where this application runs.
     */
    public function test_a_fresh_reservation_holds_in_every_timezone(): void
    {
        foreach ($this->timezones() as $tz) {
            date_default_timezone_set($tz);

            $queue = $this->queue(Job_Queue::QUEUE_TYPE_SQLITE, $this->sqlite);
            $this->sqlite->exec('DROP TABLE IF EXISTS atomtest_env_jobs');
            $queue->flushCache();

            $queue->addJob('held');
            $this->assertSame('held', $queue->getNextJobAndReserve()['payload'] ?? null, "[{$tz}] the job was not reserved");

            $this->assertSame(
                [],
                $queue->getNextJobAndReserve(),
                "[{$tz}] a reservation made moments ago was treated as abandoned — the job would run twice"
            );
        }
    }

    /**
     * And the mirror image: west of Greenwich the cutoff sat hours in the past, so a reservation
     * never expired and a worker that died took its job with it.
     */
    public function test_an_abandoned_reservation_is_recovered_in_every_timezone(): void
    {
        foreach ($this->timezones() as $tz) {
            date_default_timezone_set($tz);

            $queue = $this->queue(Job_Queue::QUEUE_TYPE_SQLITE, $this->sqlite);
            $this->sqlite->exec('DROP TABLE IF EXISTS atomtest_env_jobs');
            $queue->flushCache();

            $queue->addJob('abandoned');
            $queue->getNextJobAndReserve();

            // The worker holding it died an hour ago.
            $long_ago = gmdate('Y-m-d H:i:s', time() - 3600);
            $this->sqlite->exec("UPDATE atomtest_env_jobs SET reserved_dt = '{$long_ago}', time_to_retry_dt = '{$long_ago}'");

            $this->assertSame(
                'abandoned',
                $queue->getNextJobAndReserve()['payload'] ?? null,
                "[{$tz}] an abandoned reservation was never recovered — the job is stranded"
            );
        }
    }

    /** The helper the arithmetic now runs through, checked directly against absolute time. */
    public function test_the_clock_helper_is_an_absolute_offset(): void
    {
        foreach ($this->timezones() as $tz) {
            date_default_timezone_set($tz);

            $method = new ReflectionMethod(Job_Queue::class, 'utcTimestamp');
            $method->setAccessible(true);

            $now = strtotime($method->invoke(null, 0) . ' UTC');
            $later = strtotime($method->invoke(null, 90) . ' UTC');

            $this->assertSame(90, $later - $now, "[{$tz}] a 90-second offset was not 90 seconds");
            $this->assertEqualsWithDelta(time(), $now, 2, "[{$tz}] the helper is not on the real clock");
        }
    }

    // ---------------------------------------------------------------- the schema scope

    /** A table of the same name in a SIBLING database must not answer for this one. */
    public function test_a_table_in_another_schema_is_not_mistaken_for_this_one(): void
    {
        $pdo = $this->requireMysql();

        try {
            $pdo->exec('CREATE DATABASE IF NOT EXISTS ' . self::OTHER_SCHEMA);
            $pdo->exec('CREATE TABLE IF NOT EXISTS ' . self::OTHER_SCHEMA . '.atomtest_env_jobs (id INT PRIMARY KEY)');
        } catch (Throwable $e) {
            $this->markTestSkipped('Cannot create a sibling schema to test against: ' . $e->getMessage());
        }

        $queue = $this->queue(Job_Queue::QUEUE_TYPE_MYSQL, $pdo);

        $this->assertFalse(
            $this->invoke($queue, 'sqlTableExists', ['atomtest_env_jobs']),
            'a table in a sibling database was reported as present in this one'
        );
    }

    /**
     * The damage that follows from it: creation is skipped, and the failure surfaces later as a
     * missing table with nothing connecting it to the cause.
     */
    public function test_the_queue_still_creates_its_table_when_a_sibling_schema_has_that_name(): void
    {
        $pdo = $this->requireMysql();

        try {
            $pdo->exec('CREATE DATABASE IF NOT EXISTS ' . self::OTHER_SCHEMA);
            $pdo->exec('CREATE TABLE IF NOT EXISTS ' . self::OTHER_SCHEMA . '.atomtest_env_jobs (id INT PRIMARY KEY)');
        } catch (Throwable $e) {
            $this->markTestSkipped('Cannot create a sibling schema to test against: ' . $e->getMessage());
        }

        $queue = $this->queue(Job_Queue::QUEUE_TYPE_MYSQL, $pdo);
        $queue->addJob('payload');

        $rows = $pdo->query('SELECT COUNT(*) c FROM atomtest_env_jobs')->fetchAll(PDO::FETCH_ASSOC);
        $this->assertSame(1, (int) $rows[0]['c'], 'the queue did not create its own table');
    }

    /** SQLite's branch was already scoped — it reads that connection's own schema. Keep it so. */
    public function test_the_sqlite_existence_check_is_scoped_to_its_own_file(): void
    {
        $queue = $this->queue(Job_Queue::QUEUE_TYPE_SQLITE, $this->sqlite);

        $this->assertFalse($this->invoke($queue, 'sqlTableExists', ['atomtest_env_jobs']));

        $queue->addJob('payload');

        $this->assertTrue($this->invoke($queue, 'sqlTableExists', ['atomtest_env_jobs']));
    }
}
