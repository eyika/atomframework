<?php

namespace Eyika\Atom\Framework\Support\Database;

use Exception;
use Eyika\Atom\Framework\Support\Arr;
use Eyika\Atom\Framework\Support\Facade\DatabaseConnection;

class DB
{
    // Transaction/lifecycle flags are process-level and stay static; every other
    // query-state field is now INSTANCE state (BUG-31) so two live builders — e.g.
    // DB::table('a')->where(...) held while DB::table('b')->... runs — no longer
    // clobber each other's WHERE/ORDER/JOIN/LIMIT.
    public static bool $transaction_mode;

    protected $bind_or_filter;
    protected array|string $or_ands;
    protected array $joins = [];
    protected array|string $operators;
    protected $order;
    protected bool $for_update = false;

    private static $instantiated = false;

    protected $recordsPerPage;
    protected $table;

    public function __construct()
    {
        static::$transaction_mode = false;
        $this->or_ands = 'AND';
        $this->operators = '=';
        static::$instantiated = true;
        $this->order = '';
        $this->bind_or_filter = null;
    }

    public static function table(string $table)
    {
        $o = (new static());
        $o->table = $table;

        return $o;
    }

    public static function beginTransaction()
    {
        // Transaction state lives on the connection object (Connection::$transaction_mode),
        // not in $_SESSION (WRK-12/PERF-16) — the session global was written but never
        // read, and is process-shared under a worker.
        DatabaseConnection::beginTransaction();

        if (! self::$instantiated)
            self::$transaction_mode = true;
    }

    public static function commit()
    {
        DatabaseConnection::commit();

        if (! self::$instantiated)
            self::$transaction_mode = false;
    }

    public static function rollback()
    {
        DatabaseConnection::rollback();

        if (! self::$instantiated)
            self::$transaction_mode = false;
    }

    public static function statement(string $stmt)
    {
        $stat = DatabaseConnection::exec($stmt);

        return $stat !== false;
    }

    public static function select(string $select_stmt)
    {
        $statement = DatabaseConnection::exec($select_stmt);

        return $statement->fetchAll();
    }

    /**
     * Run a parameterized SELECT and return an array of associative rows.
     * Bindings are named (e.g. `:limit`, `:start`).
     *
     * @param string $sql
     * @param array $bind
     * @return array
     */
    public static function query(string $sql, array $bind = []): array
    {
        $statement = DatabaseConnection::exec($sql, $bind);
        if ($statement === false) {
            return [];
        }
        return $statement->fetchAll(\PDO::FETCH_ASSOC);
    }

    protected function resetInstance()
    {
        $this->bind_or_filter = null;
        $this->or_ands = '';
        $this->operators = '=';
        $this->order = '';
        $this->for_update = false;
        self::$transaction_mode = false;
    }

    /**
     * Add a pessimistic write lock (SELECT ... FOR UPDATE) to the next first()/get().
     * Use inside a transaction to serialize read-modify-write access to a row
     * (e.g. wallet balance). The flag is cleared by resetInstance() after the read.
     */
    public function lockForUpdate()
    {
        $this->for_update = true;
        return $this;
    }

    /**
     * GROUP BY one or more columns — mirrors QueryBuilder::_groupBy().
     *
     * Note there is no chainable `select()` here: DB::select() is already a static raw-SELECT
     * executor. Projection on this builder goes through the argument, `get(['a', 'b'])`.
     */
    public function groupBy($columns)
    {
        $columns = is_array($columns) ? $columns : explode(',', (string) $columns);

        $terms = array_map(
            fn ($c) => Connection::quoteQualified(trim((string) $c)),
            array_filter($columns, fn ($c) => trim((string) $c) !== '')
        );

        if (!$terms) {
            return $this;
        }

        $existing = $this->bind_or_filter['GROUP BY'] ?? '';
        $this->bind_or_filter['GROUP BY'] = $existing === ''
            ? implode(', ', $terms)
            : $existing . ', ' . implode(', ', $terms);

        return $this;
    }

    /** Filter on an aggregate — mirrors QueryBuilder::_having(). The value is always bound. */
    public function having($column, $operatorOrValue = null, $value = null)
    {
        if ($value === null) {
            $value = $operatorOrValue;
            $operator = '=';
        } else {
            $operator = $operatorOrValue;
        }

        $left = Connection::compileAggregateExpression((string) $column);
        $operator = Connection::safeComparator($operator);

        $existing = $this->bind_or_filter['HAVING'] ?? ['sql' => '', 'bind' => []];
        $param = ':having_' . count($existing['bind']);

        $existing['sql'] = ($existing['sql'] === '' ? '' : $existing['sql'] . ' AND ')
            . "$left $operator $param";
        $existing['bind'][$param] = is_bool($value) ? (int) $value : $value;

        $this->bind_or_filter['HAVING'] = $existing;

        return $this;
    }

    public function orderBy($column = "id", $direction = "ASC")
    {
        // SECURITY: escape column identifier(s) + whitelist direction (mirrors
        // QueryBuilder::orderBy) — a user-supplied sort must not inject.
        $dir = strtoupper(trim((string) $direction)) === 'DESC' ? 'DESC' : 'ASC';

        // Per-term direction + accumulation across calls, mirroring QueryBuilder::orderBy.
        $terms = array_map(
            fn($c) => Connection::quoteQualified(trim($c)) . " $dir",
            explode(',', (string) $column)
        );

        $existing = $this->bind_or_filter['ORDER BY'] ?? '';

        $this->bind_or_filter['ORDER BY'] = $existing === ''
            ? implode(', ', $terms)
            : $existing . ', ' . implode(', ', $terms);

        return $this;
    }

    public function raw(string $sql, $bind = [])
    {
        return DatabaseConnection::exec($sql, $bind);
    }

    public function create(array $values, array|string $select = '*')
    {
        if (!$id = $this->insert($values)) {
            return false;
        }
        
        $fields = $select;

        if (!$model = DatabaseConnection::fetch($this->table, ['id' => $id], $fields)) {
            return true;
        };

        return $model;
    }

    public function insert(array $values)
    {
        if (!$id = DatabaseConnection::insert($this->table, $values)) {
            return false;
        }
        return $id;
    }

    public function find(int $id, array|string $fields = '*')
    {
        return $this->_find($id, $fields);
    }

    public function exists()
    {
        return $this->count() > 0;
    }

    public function findOr($id = 0, $callable = null)
    {
        if (!$model = $this->_find($id)) {
            return is_callable($callable) ? $callable() : $model;
        }

        return $model;
    }

    public function first(array|string $fields = '*')
    {
        return $this->_find(null, $fields);
    }

    /**
     * First matching row, or the result of $callable when none is found.
     */
    public function firstOr($callable = null, array|string $fields = '*')
    {
        if (!$model = $this->_find(null, $fields)) {
            return is_callable($callable) ? $callable() : $model;
        }

        return $model;
    }

    private function _find(int|null $id, array|string $fields = '*')
    {
        $query_arr = [];

        if ($this->bind_or_filter)
            $query_arr = $this->bind_or_filter;
        
        if ($id && $id > 0)
            $query_arr['id'] = $id;

        if (!$model = DatabaseConnection::fetch($this->table, $query_arr, $fields, $this->operators, $this->or_ands, $this->for_update)) {
            $this->resetInstance();
            return false;
        }
        $this->resetInstance();
        return $model[0];
    }

    public function firstWhere($column, $operatorOrValue = null, $value = null)
    {
        return $this->where($column, $operatorOrValue, $value)->first();
    }

    /**
     * First row matching $search, or a freshly created row (search + values merged).
     * Returns a single associative row (not a list).
     */
    public function firstOrCreate($search, $keyvalues, array|string $select = '*')
    {
        $existing = $this->findByArray(array_keys($search), array_values($search), 'AND', $select);
        if ($existing) {
            return is_array($existing) ? ($existing[0] ?? $existing) : $existing;
        }

        $created = $this->create(array_merge($search, $keyvalues), $select);
        return is_array($created) ? ($created[0] ?? $created) : $created;
    }

    /**
     * First row matching $search, or the would-be attributes (search + values merged)
     * WITHOUT persisting. The static DB builder has no model instances, so a miss
     * returns a plain array rather than an unsaved model.
     */
    public function firstOrNew($search, $keyvalues, array|string $select = '*')
    {
        $existing = $this->findByArray(array_keys($search), array_values($search), 'AND', $select);
        if ($existing) {
            return is_array($existing) ? ($existing[0] ?? $existing) : $existing;
        }

        return array_merge($search, $keyvalues);
    }

    public function findBy($key, $value, array|string $select = '*')
    {
        $query_arr = $this->bind_or_filter === null ? [] : $this->bind_or_filter;

        $query_arr[$key] = $value;
    
        $fields = $select;

        if (!$model = DatabaseConnection::fetch($this->table, $query_arr, $fields, $this->operators, $this->or_ands)) {
            $this->resetInstance();
            return false;
        }
        $this->resetInstance();
        return $model;
    }

    public function findByArray($keys, $values, $or_and = "AND", $select = [])
    {
        if (count($keys) !== count($values)) {
            return false;
        }

        $query_arr = [];

        foreach ($keys as $pos => $key) {
            $query_arr[$key] = $values[$pos];
            is_string($this->or_ands) ? $this->or_ands = [$or_and] : array_push($this->or_ands, $or_and);
        }

        // Empty select → all columns (see all()).
        if (is_array($select) && count($select) === 0) {
            $select = '*';
        }

        if (!$fields = DatabaseConnection::fetch($this->table, $query_arr, $select)) {
            return false;
        }
        return $fields;
    }

    public function all($select = [])
    {
        $query_arr = [];
        if ($this->bind_or_filter)
            $query_arr = $this->bind_or_filter;

        // The raw builder has no fillable list — an empty select means "all columns".
        // Left as [] it produced `SELECT  FROM` (invalid SQL).
        if (is_array($select) && count($select) === 0) {
            $select = '*';
        }

        if (!$fields = DatabaseConnection::fetch($this->table, $query_arr, $select, $this->operators, $this->or_ands, $this->for_update)) {
            $this->resetInstance();
            return false;
        }
        $this->resetInstance();
        return $fields;
    }

    public function get($select = '*')
    {
        return $this->all($select);
    }

    public function paginate($currentPage = null, $recordsPerPage = null)
    {
        $currentPage = max(1, (int) ($currentPage ?? PaginatedData::currentPage));
        $recordsPerPage = (int) ($recordsPerPage ?? $this->recordsPerPage ?? PaginatedData::recordsPerPage);
        if ($recordsPerPage < 1) {
            $recordsPerPage = PaginatedData::recordsPerPage;
        }

        // Count matching rows WITHOUT resetting the builder, so the SAME where()
        // filter is reused for the page fetch below (mirrors QueryBuilder::_paginate).
        // The previous code called `static::$offset(...)` — a variable-variable that
        // treated $offset as a property name — and passed the table as a column.
        $totalRecords = (int) $this->_aggregate('*', 'count', false);
        $totalPages = (int) ceil($totalRecords / $recordsPerPage);
        $offset = ($currentPage - 1) * $recordsPerPage;

        $this->limit($recordsPerPage);
        $this->offset($offset);

        $data = $this->all();

        if (!$data) {
            return false;
        }
        return PaginatedData::init($data, $totalRecords, $recordsPerPage, $totalPages, $currentPage);
    }

    public function random()
    {
        $data = $this->_aggregate(method: 'random', reset_instance: false);

        return $data;
    }

    public function count($column = "*")
    {
        if (!$dat = $this->_aggregate($column)) {
            return 0;
        }
        return $dat;
    }

    public function avg($column)
    {
        if (!$dat = $this->_aggregate($column, 'avg')) {
            return 0;
        }
        return $dat;
    }

    public function max($column)
    {
        if (!$dat = $this->_aggregate($column, 'max')) {
            return 0;
        }
        return $dat;
    }
    
    public function min($column)
    {
        if (!$dat = $this->_aggregate($column, 'min')) {
            return 0;
        }
        return $dat;
    }

    public function sum($column)
    {
        if (!$dat = $this->_aggregate($column, 'sum')) {
            return 0;
        }
        return $dat;
    }

    public function group_concat($column)
    {
        if (!$dat = $this->_aggregate($column, 'group_concat')) {
            return '';
        }
        return $dat;
    }
    
    public function var_pop($column)
    {
        if (!$dat = $this->_aggregate($column, 'var_pop')) {
            return 0;
        }
        return $dat;
    }

    public function stddev($column)
    {
        if (!$dat = $this->_aggregate($column, 'stddev')) {
            return 0;
        }
        return $dat;
    }
    
    public function bit_and($column)
    {
        if (!$dat = $this->_aggregate($column, 'bit_and')) {
            return 0;
        }
        return $dat;
    }

    public function bit_or($column)
    {
        if (!$dat = $this->_aggregate($column, 'bit_or')) {
            return 0;
        }
        return $dat;
    }

    public function bit_xor($column)
    {
        if (!$dat = $this->_aggregate($column, 'bit_xor')) {
            return 0;
        }
        return $dat;
    }

    public function _aggregate($column = "*", $method = 'count', $reset_instance = true)
    {
        $query_arr = $this->bind_or_filter === null ? [] : $this->bind_or_filter;

        if ($method == 'count' && $column != '*') {
            $this->operators[] = "DISTINCT " . Connection::quoteQualified($column);
        }

        $method = $method == 'count' ? $method : $method."_".$column;

        if (!$aggregate = DatabaseConnection::{$method}($this->table, $query_arr, $this->operators, $this->or_ands)) {
            if ($reset_instance)
                $this->resetInstance();
            return false;
        }
        if ($reset_instance)
            $this->resetInstance();

        return $aggregate;
    }

    public function update(array $values, int|null $id = null)
    {
        return $this->_update($values, $id);
    }

    public function increment(string $column, int $step = 1)
    {
        $query_arr = $this->bind_or_filter === null ? [] : $this->bind_or_filter;
        $operators = $this->operators;
        $column = $this->parseColumn($column);

        if (DatabaseConnection::increment($column, $this->table, $query_arr, $operators, $this->or_ands, $step)) {
            return false;
        }
        return true;
    }

    public function decrement(string $column, int $step = 1)
    {
        $query_arr = $this->bind_or_filter === null ? [] : $this->bind_or_filter;
        $operators = $this->operators;
        $column = $this->parseColumn($column);

        if (DatabaseConnection::decrement($column, $this->table, $query_arr, $operators, $this->or_ands, $step)) {
            return false;
        }
        return true;
    }

    public function delete(int|null $id = null)
    {
        $query_arr = $this->bind_or_filter === null ? [] : $this->bind_or_filter;

        if ((int) $id > 0 && count($query_arr) < 1)
            $query_arr['id'] = $id;

        // Refuse a filterless/idless delete — it would DELETE the entire table (or,
        // with a null id, silently match nothing).
        if (count($query_arr) < 1) {
            throw new Exception('delete() requires a positive id or a where() filter; refusing to delete every row.');
        }

        $val = DatabaseConnection::remove($this->table, $query_arr, $this->operators, $this->or_ands);
        $this->resetInstance();
        return $val;
    }

    public function restore($id)
    {
        // Associative — a list wrote columns `0`/`1` and never cleared deleted_at.
        return $this->_update(['deleted_at' => null], $id);
    }

    public function limit($amount)
    {
        $this->bind_or_filter['LIMIT'] = (int) $amount;
        return $this;
    }

    public function offset($postion)
    {
        $this->bind_or_filter['OFFSET'] = (int) $postion;
        return $this;
    }

    public function where($column, $operatorOrValueOrMethod = null, $value = null)
    {
        return $this->_where($column, $operatorOrValueOrMethod, $value, 'AND');
    }
    
    public function whereLike($column, $value = null)
    {
        return $this->_where($column, 'LIKE', $value, 'AND');
    }
    
    public function whereIn($column, $values)
    {
        return $this->_where($column, 'IN', $values, 'AND');
    }
    
    public function whereNotIn($column, $values)
    {
        return $this->_where($column, 'NOT IN', $values, 'AND');
    }

    public function whereNotLike($column, $value = null)
    {
        return $this->_where($column, 'NOT LIKE', $value, 'AND');
    }

    public function whereBetween($column, array $range)
    {
        if (count($range) !== 2) {
            throw new \InvalidArgumentException('whereBetween expects a [min, max] array');
        }
        return $this->_where($column, 'BETWEEN', array_values($range), 'AND');
    }

    public function whereNotBetween($column, array $range)
    {
        if (count($range) !== 2) {
            throw new \InvalidArgumentException('whereNotBetween expects a [min, max] array');
        }
        return $this->_where($column, 'NOT BETWEEN', array_values($range), 'AND');
    }

    public function whereLessThan($column, $value = null)
    {
        return $this->_where($column, '<', $value, 'AND');
    }

    public function whereGreaterThan($column, $value = null)
    {
        return $this->_where($column, '>', $value, 'AND');
    }

    public function whereLessThanOrEqual($column, $value = null)
    {
        return $this->_where($column, '<=', $value, 'AND');
    }

    public function whereGreaterThanOrEqual($column, $value = null)
    {
        return $this->_where($column, '>=', $value, 'AND');
    }

    public function whereEqual($column, $value = null)
    {
        return $this->_where($column, '=', $value, 'AND');
    }

    public function whereNotEqual($column, $value = null)
    {
        return $this->_where($column, '!=', $value, 'AND');
    }

    public function whereNull($column)
    {
        return $this->_where($column, ' IS NULL');
    }

    public function whereNotNull($column)
    {
        return $this->_where($column, 'IS NOT NULL');
    }

    public function orWhere($column, $operatorOrValue = null, $value = null)
    {
        return $this->_where($column, $operatorOrValue, $value, 'OR');
    }
    
    public function orWhereIn($column, $values)
    {
        return $this->_where($column, 'IN', $values, 'OR');
    }
    
    public function orWhereNotIn($column, $values)
    {
        return $this->_where($column, 'NOT IN', $values, 'OR');
    }

    public function orWhereLike($column, $value = null)
    {
        return $this->_where($column, 'LIKE', $value, 'OR');
    }

    public function orWhereNotLike($column, $value = null)
    {
        return $this->_where($column, 'NOT LIKE', $value, 'OR');
    }
    
    public function orWhereLessThan($column, $value = null)
    {
        return $this->_where($column, '<', $value, 'OR');
    }

    public function orWhereGreaterThan($column, $value = null)
    {
        return $this->_where($column, '>', $value, 'OR');
    }

    public function orWhereLessThanOrEqual($column, $value = null)
    {
        return $this->_where($column, '<=', $value, 'OR');
    }

    public function orWhereGreaterThanOrEqual($column, $value = null)
    {
        return $this->_where($column, '>=', $value, 'OR');
    }

    public function orWhereEqual($column, $value = null)
    {
        return $this->_where($column, '=', $value, 'OR');
    }

    public function orWhereNotEqual($column, $value = null)
    {
        return $this->_where($column, '!=', $value, 'OR');
    }

    public function orWhereNull($column)
    {
        return $this->_where($column, ' IS NULL', boolean: 'OR');
    }

    public function orWhereNotNull($column)
    {
        return $this->_where($column, 'NOT NULL', boolean: 'OR');
    }

    /**
     * update a model
     * 
     * @param array $values
     * @param int $id
     * @param bool $internal
     * @return self|bool|array
     */
    private function _update(array $values, int|null $id = null, string|array $fields = '*')
    {   
        $query_arr = $this->bind_or_filter === null ? [] : $this->bind_or_filter;

        if ($id)
            $query_arr['id'] = $id;

        $count = DatabaseConnection::update($this->table, $query_arr, $values, $this->operators, $this->or_ands);

        if (!$model = DatabaseConnection::fetch($this->table, $query_arr, $fields, $this->operators, $this->or_ands)) {
            $this->resetInstance();
            return false;
        }

        $this->resetInstance();

        return $model[0];
    }

    private function _where(string $column, string|null $operatorOrValue = null, $value = null, $boolean = "AND")
    {
        $bind_or_filter = $this->bind_or_filter;
        if (is_array($bind_or_filter)) {
            foreach ($bind_or_filter as $key => $_value) {
                if (($key == 'LIMIT' || $key == 'OFFSET') && gettype($_value) == 'integer') {
                    throw new Exception("all where queries should come before $key queries");
                }
            }
        }
        if (is_null($value) && !is_null($operatorOrValue) && str_contains($operatorOrValue, ' NULL')) {// only column and value was given but value is like `IS NULL` or `NOT NULL`
            $value = $operatorOrValue;
        } else if (is_null($value) && Arr::exists(['!=', '==', '='], $operatorOrValue)) {
            $value = $operatorOrValue == '!=' ? 'IS NOT NULL' : 'IS NULL';
        } else if (is_null($value) && !is_null($operatorOrValue) && !str_contains($operatorOrValue, ' NULL')) {// only column and value was given
            is_string($this->operators) ? $this->operators = ['='] : array_push($this->operators, '=');
            $value = $operatorOrValue;
        } else {
            is_string($this->operators) ? $this->operators = [$operatorOrValue] : array_push($this->operators, $operatorOrValue);
        }

        is_string($this->or_ands) ? $this->or_ands = [$boolean] : array_push($this->or_ands, $boolean);
        $column = $this->parseColumn($column);

        // See QueryBuilder::__where() for rationale on the __dupN suffix.
        $bindKey = $column;
        if (is_array($this->bind_or_filter) && array_key_exists($bindKey, $this->bind_or_filter)) {
            $n = 1;
            while (array_key_exists($column . '__dup' . $n, $this->bind_or_filter)) {
                $n++;
            }
            $bindKey = $column . '__dup' . $n;
        }

        is_null($this->bind_or_filter) ? $this->bind_or_filter = array($bindKey => $value) : $this->bind_or_filter[$bindKey] = $value;

        return $this;
    }

    private function _join(string $table, string $first, string $operator, string $second, string $type = 'INNER'): static
    {
        $this->joins[] = compact('type', 'table', 'first', 'operator', 'second');
        return $this;
    }

    public function join(string $table, string $first, string $operator, string $second)
    {
        return $this->_join($table, $first, $operator, $second);
    }

    public function leftJoin(string $table, string $first, string $operator, string $second)
    {
        return $this->_join($table, $first, $operator, $second, 'LEFT');
    }

    public function rightJoin(string $table, string $first, string $operator, string $second)
    {
        return $this->_join($table, $first, $operator, $second, 'RIGHT');
    }

    public function fullOuterJoin(string $table, string $first, string $operator, string $second)
    {
        return $this->_join($table, $first, $operator, $second, 'FULL OUTER');
    }

    public function distinct($column)
    {
        $distinct = "DISTINCT " . Connection::quoteQualified($column);
        is_string($this->operators) ? $this->operators = [$distinct] : array_push($this->operators, $distinct);

        return $this;
    }

    private function parseColumn(string $column): string
    {
        if (strpos($column, '.') !== false) {
            [$relation, $field] = explode('.', $column, 2);

            $this->leftJoin("{$relation}s", $this->table.".{$relation}_id", '=', "{$relation}s.id");

            return "{$relation}s.{$field}";
        }

        return $this->table.".{$column}";
    }
}
