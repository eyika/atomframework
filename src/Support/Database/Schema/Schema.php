<?php
namespace Eyika\Atom\Framework\Support\Database\Schema;

use Eyika\Atom\Framework\Support\Facade\DatabaseConnection;

class Schema
{
    /**
     * Create a new table.
     *
     * @param string $table
     * @param callable $callback
     * @return void
     */
    public static function create(string $table, callable $callback): void
    {
        $blueprint = new Blueprint($table);
        $callback($blueprint);
        $sql = $blueprint->toSql();
        DatabaseConnection::exec($sql);
    }

    /**
     * Drop a table if it exists.
     *
     * @param string $table
     * @return void
     */
    public static function dropIfExists(string $table): void
    {
        DatabaseConnection::exec((new Blueprint($table))->rollback());
    }

    /**
     * Check if a table exists.
     *
     * @param string $table
     * @return bool
     */
    public static function hasTable(string $table): bool
    {
        $sql = "SHOW TABLES LIKE :table";
        $statement = DatabaseConnection::exec($sql, [':table' => $table]);
        return $statement->rowCount() > 0;
    }

    /**
     * Alter an existing table.
     *
     * @param string $table
     * @param callable $callback
     * @return void
     */
    public static function table(string $table, callable $callback): void
    {
        $blueprint = new Blueprint($table, true);
        $callback($blueprint);
        $sql = $blueprint->toSql();
        DatabaseConnection::exec($sql);
    }
}
