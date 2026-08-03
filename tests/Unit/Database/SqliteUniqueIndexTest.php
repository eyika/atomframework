<?php

namespace Eyika\Atom\Framework\Tests\Unit\Database;

use Eyika\Atom\Framework\Support\Database\Connection;
use Eyika\Atom\Framework\Support\Database\Schema\Blueprint;
use Eyika\Atom\Framework\Support\Database\Schema\Schema;
use Eyika\Atom\Framework\Support\Facade\DatabaseConnection;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Reported by Claude C (vendra): dropUnique() was unusable on SQLite, for two independent
 * reasons.
 *
 *  1. Name resolution ran a hard-coded INFORMATION_SCHEMA.STATISTICS query — MySQL-only — so
 *     dropUnique(['col']) could never resolve an index name on any other driver.
 *  2. Column-level ->unique() compiled to an INLINE `UNIQUE` for every grammar. On SQLite that
 *     becomes an implicit `sqlite_autoindex_*` which the engine refuses to drop, so no forward
 *     ALTER could ever remove it.
 *
 * Name lookup is now delegated to the grammar (PRAGMA on SQLite), and column-level uniques are
 * promoted to real named indexes wherever indexes are emitted separately.
 */
class SqliteUniqueIndexTest extends TestCase
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
    }

    /** @return array<int, array<string, mixed>> PRAGMA index_list rows for a table. */
    private function indexList(string $table): array
    {
        return DatabaseConnection::exec("PRAGMA index_list(\"$table\")")->fetchAll(PDO::FETCH_ASSOC);
    }

    private function indexNames(string $table): array
    {
        return array_column($this->indexList($table), 'name');
    }

    public function test_column_level_unique_becomes_a_real_named_index(): void
    {
        Schema::create('members', function (Blueprint $t) {
            $t->id();
            $t->string('email')->unique();
        });

        $names = $this->indexNames('members');

        $this->assertContains('unique_email', $names, 'column ->unique() should create a named index');
        $this->assertSame(
            [],
            array_filter($names, fn ($n) => str_starts_with($n, 'sqlite_autoindex_')),
            'no implicit autoindex should be created — those cannot be dropped'
        );
    }

    public function test_column_level_unique_still_enforces_uniqueness(): void
    {
        Schema::create('members', function (Blueprint $t) {
            $t->id();
            $t->string('email')->unique();
        });

        DatabaseConnection::exec("INSERT INTO members (email) VALUES ('a@example.com')");

        $this->expectException(\PDOException::class);
        DatabaseConnection::exec("INSERT INTO members (email) VALUES ('a@example.com')");
    }

    public function test_drop_unique_by_column_works_on_sqlite(): void
    {
        Schema::create('members', function (Blueprint $t) {
            $t->id();
            $t->string('email')->unique();
        });

        $this->assertContains('unique_email', $this->indexNames('members'));

        Schema::table('members', function (Blueprint $t) {
            $t->dropUnique(['email']);
        });

        $this->assertNotContains('unique_email', $this->indexNames('members'));

        // The constraint is genuinely gone, not merely unnamed.
        DatabaseConnection::exec("INSERT INTO members (email) VALUES ('dup@example.com')");
        DatabaseConnection::exec("INSERT INTO members (email) VALUES ('dup@example.com')");

        $count = DatabaseConnection::exec('SELECT COUNT(*) AS c FROM members')->fetch(PDO::FETCH_ASSOC);
        $this->assertEquals(2, $count['c']);
    }

    public function test_drop_unique_on_a_composite_table_level_index(): void
    {
        Schema::create('memberships', function (Blueprint $t) {
            $t->id();
            $t->integer('org_id');
            $t->integer('user_id');
            $t->unique(['org_id', 'user_id']);
        });

        Schema::table('memberships', function (Blueprint $t) {
            $t->dropUnique(['org_id', 'user_id']);
        });

        DatabaseConnection::exec('INSERT INTO memberships (org_id, user_id) VALUES (1, 1)');
        DatabaseConnection::exec('INSERT INTO memberships (org_id, user_id) VALUES (1, 1)');

        $count = DatabaseConnection::exec('SELECT COUNT(*) AS c FROM memberships')->fetch(PDO::FETCH_ASSOC);
        $this->assertEquals(2, $count['c'], 'the composite unique index should be gone');
    }

    public function test_dropping_a_unique_that_does_not_exist_fails_loudly(): void
    {
        Schema::create('members', function (Blueprint $t) {
            $t->id();
            $t->string('email');
        });

        $this->expectException(\RuntimeException::class);

        Schema::table('members', function (Blueprint $t) {
            $t->dropUnique(['email']);
        });
    }
}
