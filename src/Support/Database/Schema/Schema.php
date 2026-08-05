<?php
namespace Eyika\Atom\Framework\Support\Database\Schema;

use Eyika\Atom\Framework\Support\Database\Connection;
use Eyika\Atom\Framework\Support\Facade\DatabaseConnection;

class Schema
{
    /**
     * Create a new table. A blueprint may compile to more than one statement (e.g. SQLite emits
     * secondary indexes as their own CREATE INDEX), so every statement is executed in order.
     */
    public static function create(string $table, callable $callback): void
    {
        $blueprint = new Blueprint($table);
        $callback($blueprint);
        self::run($blueprint->compile());
    }

    /**
     * Drop a table if it exists.
     */
    public static function dropIfExists(string $table): void
    {
        DatabaseConnection::exec(Connection::grammar()->compileDropIfExists($table));
    }

    /**
     * Check if a table exists.
     */
    public static function hasTable(string $table): bool
    {
        $statement = DatabaseConnection::exec(Connection::grammar()->compileTableExists($table));

        // Decided by whether a row came back, and NOTHING else. rowCount() used to be consulted
        // first, but it is only defined for INSERT/UPDATE/DELETE — for a SELECT it is
        // driver-dependent, and pdo_sqlite answers it from sqlite3_changes(), i.e. the affected-row
        // count of the last *write* on the connection. After any INSERT that operand was already
        // true, so PHP short-circuited and this returned true for every table name, existing or
        // not. A migration guarded on hasTable() then skipped its own CREATE without error, and
        // the failure only surfaced later as "no such table".
        return $statement->fetch() !== false;
    }

    /**
     * Check if a table has the given column.
     */
    public static function columnExists(string $table, string $column): bool
    {
        $stmt = DatabaseConnection::exec(Connection::grammar()->compileColumnExists($table, $column));
        return $stmt->fetch() !== false;
    }

    /**
     * Check if a table has ALL of the given columns.
     */
    public static function columnsExists(string $table, array $columns): bool
    {
        foreach ($columns as $column) {
            if (!self::columnExists($table, $column)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Alter an existing table.
     */
    public static function table(string $table, callable $callback): void
    {
        $blueprint = new Blueprint($table, true);
        $callback($blueprint);
        // Resolve column-based dropUnique/dropIndex calls to real index names before compiling.
        $blueprint->resolveDropIndexes();
        self::run($blueprint->compile());
    }

    /** Execute a list of compiled DDL statements in order. */
    protected static function run(array $statements): void
    {
        foreach ($statements as $sql) {
            $sql = trim((string) $sql);
            if ($sql !== '') {
                DatabaseConnection::exec($sql);
            }
        }
    }
}
