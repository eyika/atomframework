<?php

namespace Eyika\Atom\Framework\Support\Facade;

use Eyika\Atom\Framework\Support\Database\Connection;
use PDO;
use PDOStatement;

/**
 * @method static Connection connect() Establish a database connection.
 * @method static Connection instance() Get the underlying PDO instance.
 * @method static PDO getPdo()
 * @method static \PDOStatement|false exec($sql, $bind = []) exec() General SQL query execution
 * @method static array now()
 * @method static Connection transaction($callback)transaction() wrap a query in a callback and run inside a transaction
 * @method static Connection beginTransaction()
 *                start a transaction chain.  
 *                subsequent queries will be executed in a transaction
 * @method static Connection commit() commit all changes made in the transaction chain
 * @method static Connection rollback() rollback all changes made in the transaction chain
 * @method static \PDOStatement|false fetch_cursor($sql_or_table, $bind_or_filter = [], $select_what = '*', string|array $operators = "=", string|array $or_ands = "AND")
 * @method static array fetch($sql_or_table, $bind_or_filter = [], $select_what = '*', array|string $operators = '=', array|string $or_ands = "AND")
 * @method static array array($sql_or_table, $bind_or_filter = [], $select_what = '*', array|string $operators = '=', array|string $or_ands = "AND")
 * @method static array key_vals($sql_or_table, $bind_or_filter = [], $select_what = '*', array|string $operators = '=', array|string $or_ands = "AND")
 * @method static int count($sql_or_table, $bind_or_filter = [], array|string $operators = '=', array|string $or_ands = "AND")
 * @method static mixed random($table, $filter = [], string|array $operators = '=', string|array $or_ands = "AND")
 * @method static \PDOStatement|false increment($column, $table, $filters, string|array $operators = '=', string|array $or_ands = "AND", $step = 1)
 * @method static \PDOStatement|false decrement($column, $table, $filters)
 * @method static \PDOStatement|false toggle($table, $filters, $column, $if, $then, string|array $operators = '=', string|array $or_ands = "AND")
 * @method static int insert($table, $data, $ignore = false) Data insertion
 * @method static void insert_update($table, $data) Insert to table or update value
 * @method static string|false multi_insert($table, $rows, $ignore = false)
 * @method static void export_csv($file, $sql_or_table, $bind_or_filter = [], $select_what = '*', $operators = [], $or_and = "AND") Data export as csv
 * @method static void export_tsv($file, $sql_or_table, $bind_or_filter = [], $select_what = '*', $operators = [], $or_and = "AND") Data export as tsv
 * @method static int update($table, $filter, $data, string|array $operators = '=', string|array $or_ands = "AND") Data update
 * @method static bool remove($table, $filter, string|array $operators = '=', string|array $or_ands = "AND")
 * @method static string|array|void get($key, $space = 'default') get a value from a db storage
 * @method static void set($key, $value, $space = 'default') set a value in a db storage
 * @method static void unset($key, $space = 'default') unset a key-value in a db storage
 * @method static void clear($space = 'default') clear an entire storage the db storage
 * @method static mixed cache($key, $populate = null, $ttl = 60, $table = '_cache') Store item in Cache storage
 * @method static bool clear_cache($table = '_cache') clear a storage from the cache
 * @method static bool uncache($key, $table = '_cache') Remove an item from the cache
 * @method static void writejob($event, $data) Write a job to the job processor stack
 * @method static mixed|void readjob($event) Read a job from the job processor stack
 * @method static mixed popjob($event) Remove a job from the processor stack
 * @method static void on($event, $cb) Run the job worker indefinately
 * @method static void auto_create($flag = true) Auto fields creation mode
 */
class DatabaseConnection extends Facade
{
    protected static function getFacadeAccessor()
    {
        static::$callStaticIfServiceMethodNotFound = true;
        return 'db.connection';
    }
}
