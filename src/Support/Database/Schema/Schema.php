<?php
namespace Eyika\Atom\Framework\Support\Database\Schema;

use Eyika\Atom\Framework\Support\Database\mysqly;

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
        mysqly::exec($sql);
    }

    /**
     * Drop a table if it exists.
     *
     * @param string $table
     * @return void
     */
    public static function dropIfExists(string $table): void
    {
        $sql = "DROP TABLE IF EXISTS `{$table}`";
        mysqly::exec($sql);
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
        $statement = mysqly::exec($sql, [':table' => $table]);
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
        mysqly::exec($sql);
    }
}
