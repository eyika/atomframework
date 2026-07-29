<?php

namespace Eyika\Atom\Framework\Tests\Unit\Database;

use Eyika\Atom\Framework\Support\Database\Connection;
use Eyika\Atom\Framework\Support\Facade\DatabaseConnection;
use Eyika\Atom\Framework\Support\Database\Schema\Blueprint;
use Eyika\Atom\Framework\Support\Database\Schema\Schema;
use PHPUnit\Framework\TestCase;

/**
 * End-to-end proof that the driver split actually works on a real (in-memory) SQLite database:
 * migrations run through Schema/Blueprint, and CRUD runs through the same Connection query layer
 * the app uses — the whole stack, on SQLite, no MySQL required. This is the capability that makes
 * fast, disposable application tests possible.
 */
class SqliteDatabaseTest extends TestCase
{
    private Connection $conn;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite is not enabled.');
        }

        // A fresh in-memory database per test; constructing the connection also makes SQLite the
        // active grammar for Schema/Blueprint and the static identifier helpers.
        $this->conn = new Connection([
            'default'     => 'sqlite',
            'connections' => ['sqlite' => ['database' => ':memory:']],
        ]);
        $this->conn->connect();
        DatabaseConnection::swap($this->conn);
    }

    private function migrateUsers(): void
    {
        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('email', 120)->unique();
            $t->boolean('active')->default(true);
            $t->integer('logins')->default(0);
            $t->timestamps();
        });
    }

    public function test_migration_creates_a_queryable_table(): void
    {
        $this->migrateUsers();

        $this->assertTrue(Schema::hasTable('users'));
        $this->assertFalse(Schema::hasTable('ghosts'));
        $this->assertTrue(Schema::columnExists('users', 'email'));
        $this->assertFalse(Schema::columnExists('users', 'nope'));
    }

    public function test_full_crud_through_the_connection(): void
    {
        $this->migrateUsers();

        // INSERT (column-list VALUES form) → auto-increment id.
        $id = DatabaseConnection::insert('users', ['name' => 'Ada', 'email' => 'ada@x.co', 'active' => true]);
        $this->assertSame('1', (string) $id);

        DatabaseConnection::insert('users', ['name' => 'Bo', 'email' => 'bo@x.co', 'active' => false]);
        $this->assertSame(2, DatabaseConnection::count('users'));

        // READ with a WHERE filter (dialect-quoted identifiers).
        $rows = DatabaseConnection::fetch('users', ['id' => 1]);
        $this->assertSame('Ada', $rows[0]['name']);
        $this->assertSame('ada@x.co', $rows[0]['email']);

        // UPDATE.
        DatabaseConnection::update('users', ['id' => 1], ['name' => 'Ada Lovelace']);
        $this->assertSame('Ada Lovelace', DatabaseConnection::fetch('users', ['id' => 1])[0]['name']);

        // Atomic INCREMENT (UPDATE ... SET col = col + n).
        DatabaseConnection::increment('logins', 'users', ['id' => 1]);
        DatabaseConnection::increment('logins', 'users', ['id' => 1], step: 4);
        $this->assertSame('5', (string) DatabaseConnection::fetch('users', ['id' => 1])[0]['logins']);

        // DELETE.
        DatabaseConnection::remove('users', ['id' => 2]);
        $this->assertSame(1, DatabaseConnection::count('users'));
    }

    public function test_unique_constraint_is_enforced(): void
    {
        $this->migrateUsers();
        DatabaseConnection::insert('users', ['name' => 'A', 'email' => 'dup@x.co']);

        $this->expectException(\Throwable::class);
        // Second row with the same email violates the UNIQUE column — SQLite must reject it.
        DatabaseConnection::exec('INSERT INTO "users" ("name","email") VALUES (:n,:e)', [':n' => 'B', ':e' => 'dup@x.co']);
    }

    public function test_alter_add_column_then_query_it(): void
    {
        $this->migrateUsers();
        DatabaseConnection::insert('users', ['name' => 'Ada', 'email' => 'ada@x.co']);

        Schema::table('users', function (Blueprint $t) {
            $t->string('phone')->nullable();
        });

        $this->assertTrue(Schema::columnExists('users', 'phone'));
        DatabaseConnection::update('users', ['id' => 1], ['phone' => '555-0100']);
        $this->assertSame('555-0100', DatabaseConnection::fetch('users', ['id' => 1])[0]['phone']);
    }

    public function test_change_column_via_table_rebuild_preserves_data(): void
    {
        $this->migrateUsers();
        DatabaseConnection::insert('users', ['name' => 'Ada', 'email' => 'ada@x.co', 'logins' => 7]);

        // MODIFY on SQLite triggers the 12-step table rebuild; existing rows must survive.
        Schema::table('users', function (Blueprint $t) {
            $t->modifyColumn('name', 'string', ['length' => 500]);
        });

        $row = DatabaseConnection::fetch('users', ['id' => 1])[0];
        $this->assertSame('Ada', $row['name']);
        $this->assertSame('7', (string) $row['logins']);
        $this->assertSame('ada@x.co', $row['email']);
    }
}
