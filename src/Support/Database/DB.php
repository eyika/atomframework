<?php

namespace Eyika\Atom\Framework\Support\Database;

use Exception;
use Eyika\Atom\Framework\Exceptions\NotImplementedException;
use Eyika\Atom\Framework\Support\Facade\DatabaseConnection;

class DB
{
    public static bool $transaction_mode;

    protected static $bind_or_filter;
    protected static array|string $or_ands;
    protected static  array|string $operators;
    protected static $order;

    private static $instantiated = false;

    protected static $recordsPerPage;
    protected static $table;

    public function __construct()
    {
        static::$transaction_mode = false;
        static::$or_ands = 'AND';
        static::$operators = '=';
        static::$instantiated = true;
        static::$order = '';
        static::$bind_or_filter = null;
    }

    public static function table(string $table)
    {
        $o = (new static());
        $o::$table = $table;

        return $o;
    }

    public static function beginTransaction()
    {
        DatabaseConnection::beginTransaction();
        $_SESSION['transaction_mode'] = true;

        if (! self::$instantiated)
            self::$transaction_mode = true;
    }

    public static function commit()
    {
        DatabaseConnection::commit();
        $_SESSION['transaction_mode'] = false;

        if (! self::$instantiated)
            self::$transaction_mode = false;
    }

    public static function rollback()
    {
        DatabaseConnection::rollback();
        $_SESSION['transaction_mode'] = false;

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

    protected function resetInstance()
    {
        static::$bind_or_filter = null;
        static::$or_ands = '';
        static::$operators = '=';
        static::$order = '';
        self::$transaction_mode = false;
    }

    public function orderBy($column = "id", $direction = "ASC")
    {
        static::$order = "$column $direction";
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

        if (!$model = DatabaseConnection::fetch(static::$table, ['id' => $id], $fields)) {
            return true;
        };

        return $model;
    }

    public function insert(array $values)
    {
        if (!$id = DatabaseConnection::insert(static::$table, $values)) {
            return false;
        }
        return $id;
    }

    public function find(int $id, array|string $fields = '*')
    {
        return static::_find($id, $fields);
    }

    public function exists()
    {
        return static::count() > 0;
    }

    public function findOr($id = 0, $callable = null)
    {
        throw new NotImplementedException('oops! this feature is yet to be implemented');
    }

    public function first(array|string $fields = '*')
    {
        return static::_find(null, $fields);
    }

    private function _find(int|null $id, array|string $fields = '*')
    {
        $query_arr = [];

        if (static::$bind_or_filter)
            $query_arr = static::$bind_or_filter;
        
        if ($id && $id > 0)
            $query_arr['id'] = $id;

        if (!$model = DatabaseConnection::fetch(static::$table, $query_arr, $fields, static::$operators, static::$or_ands)) {
            static::resetInstance();
            return false;
        }
        static::resetInstance();
        return $model[0];
    }

    public function firstWhere($column, $operatorOrValue = null, $value = null, $id = 1)
    {
        throw new NotImplementedException('oops! this feature is yet to be implemented');
    }

    public function firstOrCreate($search, $keyvalues, array|string $select = '*')
    {
        throw new NotImplementedException('oops! this feature is yet to be implemented');
    }

    public function firstOrNew($search, $keyvalues, array|string $select = '*')
    {
        throw new NotImplementedException('oops! this feature is yet to be implemented');
    }

    public function findBy($key, $value, array|string $select = '*')
    {
        $query_arr = static::$bind_or_filter === null ? [] : static::$bind_or_filter;

        $query_arr[$key] = $value;
        if (static::$order !== "")
            $query_arr['order_by'] = static::$order;
    
        $fields = $select;

        if (!$model = DatabaseConnection::fetch(static::$table, $query_arr, $fields, static::$operators, static::$or_ands)) {
            static::resetInstance();
            return false;
        }
        static::resetInstance();
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
            is_string(static::$or_ands) ? static::$or_ands = [$or_and] : array_push(static::$or_ands, $or_and);
        }
        
        if (!$fields = DatabaseConnection::fetch(static::$table, $query_arr, $select)) {
            return false;
        }
        return $fields;
    }

    public function all($select = [])
    {
        $query_arr = [];
        if (static::$bind_or_filter)
            $query_arr = static::$bind_or_filter;

        if (static::$order !== "")
            $query_arr['order_by'] = static::$order;

        if (!$fields = DatabaseConnection::fetch(static::$table, $query_arr, $select, static::$operators, static::$or_ands)) {
            static::resetInstance();
            return false;
        }
        static::resetInstance();
        return $fields;
    }

    // public function with($model)
    // {
    //     throw new NotImplementedException('method not fully implemented');
    //     static::$with_model_name = $model;
    // }

    public function get($select = '*')
    {
        return static::all($select);
    }

    public function paginate($currentPage = null, $recordsPerPage = null)
    {
        $currentPage = $currentPage ?? 1;
        $recordsPerPage = $recordsPerPage ?? static::$recordsPerPage;
        $totalRecords = static::count(static::$table, 'id');
        // Calculate total pages
        $totalPages = ceil($totalRecords / $recordsPerPage);
        // Calculate the offset
        $offset = ($currentPage - 1) * $recordsPerPage;

        static::limit($recordsPerPage);
        static::$offset($offset);

        $data = static::all(static::$table);

        if (!$data) {
            return false;
        }
        return PaginatedData::init($data, $totalRecords, $recordsPerPage, $totalPages, $currentPage);
    }

    // public function count($column = "*")
    // {
    //     return static::_count(static::$table, $column);
    // }

    // public function _count($column = "*", $reset_instance = true)
    // {
    //     $query_arr = static::$bind_or_filter === null ? [] : static::$bind_or_filter;

    //     $i = 0;
    //     // foreach ($keys as $key) {
    //     //     $query_arr[$key] = $values[$i];
    //     //     $i++;
    //     // }

    //     if (!$count = DatabaseConnection::count(static::$table, $query_arr, static::$operators, static::$or_ands)) {
    //         if ($reset_instance)
    //             static::resetInstance();
    //         return false;
    //     }
    //     if ($reset_instance)
    //         static::resetInstance();

    //     return $count;
    // }


    public function random()
    {
        $data = static::_aggregate(method: 'random', reset_instance: false);

        return $data;
    }

    public function count($column = "*")
    {
        if (!$dat = static::_aggregate($column)) {
            return 0;
        }
        return $dat;
    }

    public function avg($column)
    {
        if (!$dat = static::_aggregate($column, 'avg')) {
            return 0;
        }
        return $dat;
    }

    public function max($column)
    {
        if (!$dat = static::_aggregate($column, 'max')) {
            return 0;
        }
        return $dat;
    }
    
    public function min($column)
    {
        if (!$dat = static::_aggregate($column, 'min')) {
            return 0;
        }
        return $dat;
    }

    public function sum($column)
    {
        if (!$dat = static::_aggregate($column, 'sum')) {
            return 0;
        }
        return $dat;
    }

    public function group_concat($column)
    {
        if (!$dat = static::_aggregate($column, 'group_concat')) {
            return '';
        }
        return $dat;
    }
    
    public function var_pop($column)
    {
        if (!$dat = static::_aggregate($column, 'var_pop')) {
            return 0;
        }
        return $dat;
    }

    public function stddev($column)
    {
        if (!$dat = static::_aggregate($column, 'stddev')) {
            return 0;
        }
        return $dat;
    }
    
    public function bit_and($column)
    {
        if (!$dat = static::_aggregate($column, 'bit_and')) {
            return 0;
        }
        return $dat;
    }

    public function bit_or($column)
    {
        if (!$dat = static::_aggregate($column, 'bit_or')) {
            return 0;
        }
        return $dat;
    }

    public function bit_xor($column)
    {
        if (!$dat = static::_aggregate($column, 'bit_xor')) {
            return 0;
        }
        return $dat;
    }

    public function _aggregate($column = "*", $method = 'count', $reset_instance = true)
    {
        $query_arr = static::$bind_or_filter === null ? [] : static::$bind_or_filter;

        if ($method == 'count' && $column != '*') {
            static::$operators[] = "DISTINCT $column";
        }

        $method = $method == 'count' ? $method : $method."_".$column;

        if (!$aggregate = DatabaseConnection::{$method}(static::$table, $query_arr, static::$operators, static::$or_ands)) {
            if ($reset_instance)
                static::resetInstance();
            return false;
        }
        if ($reset_instance)
            static::resetInstance();

        return $aggregate;
    }

    public function update(array $values, int|null $id = null)
    {
        return static::_update($values, $id);
    }

    public function increment(string $column, int $step = 1)
    {
        $query_arr = static::$bind_or_filter === null ? [] : static::$bind_or_filter;
        $operators = static::$operators;

        return DatabaseConnection::increment($column, static::$table, $query_arr, $operators, static::$or_ands, $step);
    }

    public function delete(int|null $id = null)
    {
        $query_arr = static::$bind_or_filter === null ? [] : static::$bind_or_filter;

        if ($id !== 0 && count($query_arr) < 1)
            $query_arr['id'] = $id;

        $val = DatabaseConnection::remove(static::$table, $query_arr, static::$operators, static::$or_ands);
        static::resetInstance();
        return $val;
    }

    public function restore($id)
    {
        // if (!static::$child->softdeletes) {
        //     throw new Exception("this model does not support soft deleting");
        // }

        return static::_update(['deleted_at', null], $id);
    }

    public function limit($amount)
    {
        static::$bind_or_filter['LIMIT'] = $amount;
        return $this;
    }

    public function offset($postion)
    {
        static::$bind_or_filter['OFFSET'] = $postion;
        return $this;
    }

    public function where($column, $operatorOrValueOrMethod = null, $value = null)
    {
        return static::_where($column, $operatorOrValueOrMethod, $value, 'AND');
    }
    
    public function whereLike($column, $value = null)
    {
        return static::_where($column, 'LIKE', $value, 'AND');
    }

    public function whereNotLike($column, $value = null)
    {
        return static::_where($column, 'NOT LIKE', $value, 'AND');
    }

    public function whereLessThan($column, $value = null)
    {
        return static::_where($column, '<', $value, 'AND');
    }

    public function whereGreaterThan($column, $value = null)
    {
        return static::_where($column, '>', $value, 'AND');
    }

    public function whereLessThanOrEqual($column, $value = null)
    {
        return static::_where($column, '<=', $value, 'AND');
    }

    public function whereGreaterThanOrEqual($column, $value = null)
    {
        return static::_where($column, '>=', $value, 'AND');
    }

    public function whereEqual($column, $value = null)
    {
        return static::_where($column, '=', $value, 'AND');
    }

    public function whereNotEqual($column, $value = null)
    {
        return static::_where($column, '!=', $value, 'AND');
    }

    public function whereNull($column)
    {
        return static::_where($column, ' IS NULL');
    }

    public function whereNotNull($column)
    {
        return static::_where($column, 'NOT NULL');
    }

    public function orWhere($column, $operatorOrValue = null, $value = null)
    {
        return static::_where($column, $operatorOrValue, $value, 'OR');
    }

    public function orWhereLike($column, $value = null)
    {
        return static::_where($column, 'LIKE', $value, 'OR');
    }

    public function orWhereNotLike($column, $value = null)
    {
        return static::_where($column, 'NOT LIKE', $value, 'OR');
    }
    
    public function orWhereLessThan($column, $value = null)
    {
        return static::_where($column, '<', $value, 'OR');
    }

    public function orWhereGreaterThan($column, $value = null)
    {
        return static::_where($column, '>', $value, 'OR');
    }

    public function orWhereLessThanOrEqual($column, $value = null)
    {
        return static::_where($column, '<=', $value, 'OR');
    }

    public function orWhereGreaterThanOrEqual($column, $value = null)
    {
        return static::_where($column, '>=', $value, 'OR');
    }

    public function orWhereEqual($column, $value = null)
    {
        return static::_where($column, '=', $value, 'OR');
    }

    public function orWhereNotEqual($column, $value = null)
    {
        return static::_where($column, '!=', $value, 'OR');
    }

    public function orWhereNull($column)
    {
        return static::_where($column, ' IS NULL', boolean: 'OR');
    }

    public function orWhereNotNull($column)
    {
        return static::_where($column, 'NOT NULL', boolean: 'OR');
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
        $query_arr = static::$bind_or_filter === null ? [] : static::$bind_or_filter;

        if ($id)
            $query_arr['id'] = $id;

        $count = DatabaseConnection::update(static::$table, $query_arr, $values, static::$operators, static::$or_ands);

        if (!$model = DatabaseConnection::fetch(static::$table, $query_arr, $fields, static::$operators, static::$or_ands)) {
            static::resetInstance();
            return false;
        }

        static::resetInstance();

        return $model[0];
    }

    private function _where(string $column, string|null $operatorOrValue = null, $value = null, $boolean = "AND")
    {
        $bind_or_filter = static::$bind_or_filter;
        if (is_array($bind_or_filter)) {
            foreach ($bind_or_filter as $key => $_value) {
                if (($key == 'LIMIT' || $key == 'OFFSET') && gettype($_value) == 'integer') {
                    throw new Exception("all where queries should come before $key queries");
                }
            }
        }
        if (is_null($value) && !is_null($operatorOrValue) && str_contains($operatorOrValue, ' NULL')) {// only column and value was given but value is like `IS NULL` or `NOT NULL`
            is_string(static::$operators) ? static::$operators = [$operatorOrValue] : array_push(static::$operators, $operatorOrValue);
        }
        else if (is_null($value) && !is_null($operatorOrValue) && !str_contains($operatorOrValue, ' NULL')) {// only column and value was given
            is_string(static::$operators) ? static::$operators = ['='] : array_push(static::$operators, '=');
            $value = $operatorOrValue;
        } else {
            is_string(static::$operators) ? static::$operators = [$operatorOrValue] : array_push(static::$operators, $operatorOrValue);
        }

        is_string(static::$or_ands) ? static::$or_ands = [$boolean] : array_push(static::$or_ands, $boolean);
        is_null(static::$bind_or_filter) ? static::$bind_or_filter = array($column => $value) : static::$bind_or_filter[$column] = $value;

        return $this;
    }

    public function distinct($column)
    {
        is_string(static::$operators) ? static::$operators = ["DISTINCT `$column`"] : array_push(static::$operators, "DISTINCT `$column`");

        return $this;
    }
}
