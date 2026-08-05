<?php

namespace Eyika\Atom\Framework\Tests\Unit\Database;

use Eyika\Atom\Framework\Support\Database\Connection;
use Eyika\Atom\Framework\Support\Database\Schema\Schema;
use Eyika\Atom\Framework\Support\Facade\DatabaseConnection;
use PHPUnit\Framework\TestCase;

/**
 * Reported by Claude A (backtestfx): a migration guarded on `Schema::hasTable()` silently
 * skipped its own CREATE, and the failure only surfaced later as "no such table" at runtime.
 *
 * `hasTable()` used to return `$statement->rowCount() > 0 || $statement->fetch() !== false`.
 * rowCount() is only defined for INSERT/UPDATE/DELETE; for a SELECT it is driver-dependent, and
 * pdo_sqlite answers it from sqlite3_changes() — the affected-row count of the last *write* on
 * that connection. Once any migration inserted a row, the left operand was already true, PHP
 * short-circuited, and the real lookup never ran: every table name reported as existing.
 *
 * Nothing threw, because reporting "it's already there" is exactly what a guard expects to hear.
 *
 * The compiled SQL was always correct — `columnExists()`, directly below it, tested the same kind
 * of result with `fetch() !== false` alone and was never affected.
 */
class SchemaHasTableTest extends TestCase
{
    private Connection $conn;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite is not enabled.');
        }

        $this->conn = new Connection([
            'default'     => 'sqlite',
            'connections' => ['sqlite' => ['database' => ':memory:']],
        ]);
        $this->conn->connect();
        DatabaseConnection::swap($this->conn);

        DatabaseConnection::exec('CREATE TABLE schema_probe (id INTEGER PRIMARY KEY, name TEXT)');
    }

    public function test_reports_true_for_an_existing_table(): void
    {
        $this->assertTrue(Schema::hasTable('schema_probe'));
    }

    public function test_reports_false_for_a_missing_table(): void
    {
        $this->assertFalse(Schema::hasTable('definitely_not_a_table'));
    }

    /**
     * The regression itself. A preceding write poisons rowCount(), so a missing table must still
     * report false AFTER an INSERT — which is the state every real migration run is in.
     */
    public function test_a_preceding_write_does_not_make_every_table_exist(): void
    {
        DatabaseConnection::exec("INSERT INTO schema_probe (name) VALUES ('row-one')");

        $this->assertFalse(
            Schema::hasTable('definitely_not_a_table'),
            'hasTable() returned true for a table that does not exist — a write earlier in the ' .
            'migration run left a non-zero rowCount() behind'
        );
    }

    /** A multi-row write leaves a larger stale count; the answer must not depend on it at all. */
    public function test_survives_a_multi_row_write(): void
    {
        DatabaseConnection::exec("INSERT INTO schema_probe (name) VALUES ('a')");
        DatabaseConnection::exec("INSERT INTO schema_probe (name) VALUES ('b')");
        DatabaseConnection::exec("UPDATE schema_probe SET name = 'c'");

        $this->assertFalse(Schema::hasTable('still_not_a_table'));
        $this->assertTrue(Schema::hasTable('schema_probe'));
    }

    /** The guard shape migrations actually use, end to end — this is what broke in the field. */
    public function test_migration_guard_creates_the_table_it_was_told_to(): void
    {
        DatabaseConnection::exec("INSERT INTO schema_probe (name) VALUES ('prior-write')");

        if (!Schema::hasTable('rate_limits')) {
            DatabaseConnection::exec('CREATE TABLE rate_limits (id INTEGER PRIMARY KEY, bucket TEXT)');
        }

        // Throws "no such table" if the guard wrongly skipped the create.
        DatabaseConnection::exec("INSERT INTO rate_limits (bucket) VALUES ('ip:127.0.0.1')");
        $this->assertTrue(Schema::hasTable('rate_limits'));
    }

    /** columnExists() was already correct; pin it so it cannot drift into the same shape. */
    public function test_column_exists_is_unaffected_by_a_preceding_write(): void
    {
        DatabaseConnection::exec("INSERT INTO schema_probe (name) VALUES ('row')");

        $this->assertTrue(Schema::columnExists('schema_probe', 'name'));
        $this->assertFalse(Schema::columnExists('schema_probe', 'no_such_column'));
    }
}
