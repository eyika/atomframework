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
        $sql = "SHOW TABLES LIKE '$table'";
        $statement = DatabaseConnection::exec($sql);
        return $statement->rowCount() > 0;
    }

    /**
     * Check if a table exists.
     *
     * @param string $table
     * @return bool
     */
    public static function columnExists(string $table, string $column): bool
    {
        $stmt = DatabaseConnection::query("SHOW COLUMNS FROM `$table` LIKE '$column'");
        return $stmt->fetch() !== false;
    }

    /**
     * Check if a table has given column names.
     *
     * @param array $table
     * @return bool
     */
    public static function columnsExists(string $table, array $columns): bool
    {
        $column = array_pop($columns);
        $q_string = "SHOW COLUMNS FROM `$table` LIKE '$column'";

        foreach ($columns as $_column) {
            $q_string .= " OR LIKE '$_column'";
        }
        $stmt = DatabaseConnection::query($q_string);
        return $stmt->fetch() !== false;
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
