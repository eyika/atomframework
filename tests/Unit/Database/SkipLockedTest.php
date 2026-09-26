<?php

namespace Eyika\Atom\Framework\Tests\Unit\Database;

use Eyika\Atom\Framework\Support\Database\Connection;
use Eyika\Atom\Framework\Support\Database\Grammars\MySqlGrammar;
use Eyika\Atom\Framework\Support\Database\Grammars\PostgresGrammar;
use Eyika\Atom\Framework\Support\Database\Grammars\SqliteGrammar;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;

/**
 * Records the SQL a connection compiles, without a server.
 *
 * `exec()` is the single point every compiled statement passes through, so overriding it captures
 * the text and stops short of needing a database. The capability answer is injected rather than
 * probed, because the whole point is to check both branches.
 */
class SqlCapturingConnection extends Connection
{
    public array $statements = [];
    private bool $canSkipLock;

    public function __construct(array $config, bool $canSkipLock)
    {
        parent::__construct($config);
        $this->canSkipLock = $canSkipLock;
    }

    public function supportsSkipLocked(): bool
    {
        return $this->canSkipLock;
    }

    public function exec($sql, $bind = [])
    {
        $this->statements[] = $sql;

        return null;
    }
}

/**
 * Reported by a downstream consumer auditing the job queue against Solid Queue: the framework can
 * already write `SELECT … FOR UPDATE SKIP LOCKED` — `Connection::readjob()` does it for the
 * unrelated `_queue` table — but **no application on this framework could express it**, because
 * `Grammar::compileForUpdate()` returned `' FOR UPDATE'` and nothing else and the builder had no
 * way to ask for more.
 *
 * The distinction is not cosmetic. Under contention `FOR UPDATE` queues every worker behind the
 * holder of the same row; `SKIP LOCKED` steps over it, so N workers claim N different rows. That is
 * the difference between O(N) wasted reads per claim and O(1), and it is the primitive both Solid
 * Queue and Shopify's reservation work are built on.
 *
 * **Availability is the gate**, and it is asked of the server rather than read off its version
 * string — see `Connection::supportsSkipLocked()` for why that distinction matters for MariaDB in
 * particular. Where the server cannot do it the read degrades to a plain `FOR UPDATE`: correct, and
 * slower, which is the right way round for a clause a caller cannot know the support for.
 *
 * These assert the emitted SQL **as text**. On SQLite a lock compiles away to an empty string, so a
 * behavioural test there cannot tell a correct lock from a missing one — the consumer made exactly
 * this point, and it is why the queue's own concurrency test needs a real server.
 */
class SkipLockedTest extends TestCase
{
    /**
     * Put the shared grammar back.
     *
     * `Connection::__construct()` publishes its grammar to a STATIC, so merely constructing a
     * SQLite-configured connection here re-points the identifier quoting every later test in the
     * process sees — which is precisely how this suite started failing two unrelated escaping
     * tests that run alphabetically after this one. The constructor's side effect is why
     * `Connection::makePdo()` is static and constructs nothing.
     */
    protected function tearDown(): void
    {
        new Connection($this->config('mysql'));

        parent::tearDown();
    }

    private function config(string $driver): array
    {
        return [
            'default' => $driver,
            'connections' => [$driver => ['driver' => $driver, 'database' => 'test']],
        ];
    }

    private function lockSuffix(string $driver, int $mode, bool $capable): string
    {
        $connection = new SqlCapturingConnection($this->config($driver), $capable);
        $connection->fetch_cursor('widgets', ['id' => 1], '*', '=', 'AND', [], $mode);

        $this->assertNotEmpty($connection->statements, 'no SQL was compiled');
        $sql = $connection->statements[0];

        foreach ([' FOR UPDATE SKIP LOCKED', ' FOR UPDATE'] as $suffix) {
            if (str_ends_with(rtrim($sql), rtrim($suffix))) {
                return trim($suffix);
            }
        }

        return '';
    }

    // ---------------------------------------------------------------- the grammar

    public function test_the_base_grammar_can_express_both_lock_modes(): void
    {
        $grammar = new MySqlGrammar();

        $this->assertSame(' FOR UPDATE', $grammar->compileForUpdate());
        $this->assertSame(' FOR UPDATE SKIP LOCKED', $grammar->compileForUpdate(true));
    }

    public function test_postgres_expresses_both_lock_modes(): void
    {
        $grammar = new PostgresGrammar();

        $this->assertSame(' FOR UPDATE', $grammar->compileForUpdate());
        $this->assertSame(' FOR UPDATE SKIP LOCKED', $grammar->compileForUpdate(true));
    }

    /** SQLite has no row locks at all, so both modes must stay empty rather than emit nonsense. */
    public function test_sqlite_compiles_every_lock_away(): void
    {
        $grammar = new SqliteGrammar();

        $this->assertSame('', $grammar->compileForUpdate());
        $this->assertSame('', $grammar->compileForUpdate(true));
    }

    // ---------------------------------------------------------------- the emitted statement

    public function test_an_unlocked_read_carries_no_lock(): void
    {
        $this->assertSame('', $this->lockSuffix('mysql', Connection::LOCK_NONE, true));
    }

    public function test_lock_for_update_emits_a_plain_lock(): void
    {
        $this->assertSame('FOR UPDATE', $this->lockSuffix('mysql', Connection::LOCK_UPDATE, true));
    }

    public function test_skip_locked_reaches_the_emitted_sql(): void
    {
        $this->assertSame(
            'FOR UPDATE SKIP LOCKED',
            $this->lockSuffix('mysql', Connection::LOCK_UPDATE_SKIP_LOCKED, true)
        );
    }

    /**
     * The compatibility guarantee: asking for SKIP LOCKED on a server without it must produce a
     * correct query, not a syntax error. MySQL 5.7 and MariaDB below 10.6 have to keep working.
     */
    public function test_skip_locked_degrades_where_the_server_cannot_do_it(): void
    {
        $this->assertSame(
            'FOR UPDATE',
            $this->lockSuffix('mysql', Connection::LOCK_UPDATE_SKIP_LOCKED, false)
        );
    }

    public function test_sqlite_emits_no_lock_even_when_asked(): void
    {
        $this->assertSame('', $this->lockSuffix('sqlite', Connection::LOCK_UPDATE_SKIP_LOCKED, true));
    }

    // ---------------------------------------------------------------- the builders

    /**
     * A `bool` type on the parameter that carries the mode would coerce it to `true` and lose SKIP
     * LOCKED with no error at all — the exact shape of silent-downgrade bug this is meant to avoid.
     */
    public function test_the_lock_parameter_can_carry_a_mode_rather_than_only_a_flag(): void
    {
        foreach (['fetch', 'fetch_cursor'] as $method) {
            $parameter = null;
            foreach ((new ReflectionMethod(Connection::class, $method))->getParameters() as $candidate) {
                if ($candidate->getName() === 'lock') {
                    $parameter = $candidate;
                }
            }

            $this->assertNotNull($parameter, "{$method}() has no \$lock parameter");
            $this->assertStringContainsString(
                'int',
                (string) $parameter->getType(),
                "{$method}(\$lock) is not wide enough to carry a lock mode, so SKIP LOCKED would be coerced away"
            );
        }
    }

    public function test_the_db_builder_records_the_skip_locked_mode(): void
    {
        $builder = \Eyika\Atom\Framework\Support\Database\DB::table('widgets')->skipLocked();

        $property = new ReflectionProperty($builder, 'for_update');
        $property->setAccessible(true);

        $this->assertSame(Connection::LOCK_UPDATE_SKIP_LOCKED, $property->getValue($builder));
    }

    public function test_the_db_builder_still_records_a_plain_lock(): void
    {
        $builder = \Eyika\Atom\Framework\Support\Database\DB::table('widgets')->lockForUpdate();

        $property = new ReflectionProperty($builder, 'for_update');
        $property->setAccessible(true);

        $this->assertSame(Connection::LOCK_UPDATE, $property->getValue($builder));
    }
}
