<?php

namespace Eyika\Atom\Framework\Support\Database;

use Exception;
use Eyika\Atom\Framework\Exceptions\NotImplementedException;

class DB
{
    public static bool $transaction_mode;

    protected static $bind_or_filter;
    protected static array|string $or_ands;
    protected static array|string $operators;
    protected static $order;

    private static $instantiated = false;

    protected static $recordsPerPage;

    public function __construct()
    {
        static::$transaction_mode = false;
        static::$or_ands = 'AND';
        static::$operators = '=';
        static::$instantiated = true;
    }

    public static function init()
    {
        return new static();
    }

    public static function beginTransaction()
    {
        mysqly::beginTransaction();
        $_SESSION['transaction_mode'] = true;
        self::$transaction_mode = true;
    }

    public static function commit()
    {
        mysqly::commit();
        $_SESSION['transaction_mode'] = false;
        self::$transaction_mode = false;
    }

    public static function rollback()
    {
        mysqly::rollback();
        $_SESSION['transaction_mode'] = false;
        self::$transaction_mode = false;
    }

    protected static function resetInstance()
    {
        static::$bind_or_filter = null;
        static::$or_ands = '';
        static::$operators = '=';
        static::$order = '';
        static::$transaction_mode = false;
    }

    public static function orderBy($column = "id", $direction = "ASC")
    {
        static::$order = "$column $direction";
        return new static;
    }

    public static function raw(string $sql, $bind)
    {
        return mysqly::exec($sql, $bind);
    }

    public static function create(string $table, array $values, array|string $select = '*')
    {
        if (! self::$instantiated)
            static::init();
        if (!$id = mysqly::insert($table, $values)) {
            return false;
        }
        
        $fields = $select;

        if (!$model = mysqly::fetch($table, ['id' => $id], $fields)) {
            return true;
        };

        return $model;
    }

    public static function find(string $table, $id, array|string $fields = '*')
    {
        if (! self::$instantiated)
            static::init();
        $query_arr = [];
        
        if ($id > 0)
            $query_arr['id'] = $id;

        if (!$model = mysqly::fetch($table, $query_arr, $fields, static::$operators, static::$or_ands)) {
            static::resetInstance();
            return false;
        }
        static::resetInstance();
        return $model[0];
    }

    public static function findOr(string $table, $id = 0, $callable = null)
    {
        throw new NotImplementedException('oops! this feature is yet to be implemented');
    }

    public static function first(string $table, $id = 1, array|string $fields = '*')
    {
        return static::find($table, $id, $fields);
    }

    public static function firstWhere(string $table, $column, $operatorOrValue = null, $value = null, $id = 1)
    {
        throw new NotImplementedException('oops! this feature is yet to be implemented');
    }

    public static function firstOrCreate(string $table, $search, $keyvalues, array|string $select = '*')
    {
        throw new NotImplementedException('oops! this feature is yet to be implemented');
    }

    public static function firstOrNew($search, $keyvalues, array|string $select = '*')
    {
        throw new NotImplementedException('oops! this feature is yet to be implemented');
    }

    public static function findBy(string $table, $key, $value, array|string $select = '*')
    {
        if (! self::$instantiated)
            static::init();
        $query_arr = static::$bind_or_filter === null ? [] : static::$bind_or_filter;

        $query_arr[$key] = $value;
        if (static::$order !== "")
            $query_arr['order_by'] = static::$order;
    
        $fields = $select;

        if (!$model = mysqly::fetch($table, $query_arr, $fields, static::$operators, static::$or_ands)) {
            static::resetInstance();
            return false;
        }
        static::resetInstance();
        return $model;
    }

    public static function findByArray(string $table, $keys, $values, $or_and = "AND", $select = [])
    {
        if (! self::$instantiated)
            static::init();
        if (count($keys) !== count($values)) {
            return false;
        }

        $query_arr = [];

        foreach ($keys as $pos => $key) {
            $query_arr[$key] = $values[$pos];
            is_string(static::$or_ands) ? static::$or_ands = [$or_and] : array_push(static::$or_ands, $or_and);
        }
        
        if (!$fields = mysqly::fetch($table, $query_arr, $select)) {
            return false;
        }
        return $fields;
    }

    public static function all(string $table, $select = [])
    {
        if (! self::$instantiated)
            static::init();
        $query_arr = [];
        if (static::$bind_or_filter)
            $query_arr = static::$bind_or_filter;

        if (static::$order !== "")
            $query_arr['order_by'] = static::$order;

        if (!$fields = mysqly::fetch($table, $query_arr, $select, static::$operators, static::$or_ands)) {
            static::resetInstance();
            return false;
        }
        static::resetInstance();
        return $fields;
    }

    // public static function with($model)
    // {
    //     throw new NotImplementedException('method not fully implemented');
    //     static::$with_model_name = $model;
    // }

    public static function get(string $table, $select = '*')
    {
        return static::all($table, $select);
    }

    public static function paginate(string $table, $currentPage = null, $recordsPerPage = null)
    {
        $currentPage = $currentPage ?? 1;
        $recordsPerPage = $recordsPerPage ?? static::$recordsPerPage;
        $totalRecords = static::count($table, 'id');
        // Calculate total pages
        $totalPages = ceil($totalRecords / $recordsPerPage);
        // Calculate the offset
        $offset = ($currentPage - 1) * $recordsPerPage;

        static::limit($recordsPerPage);
        static::$offset($offset);

        $data = static::all($table);

        if (!$data) {
            return false;
        }
        return PaginatedData::init($data, $totalRecords, $recordsPerPage, $totalPages, $currentPage);
    }

    public static function random()
    {
        throw new NotImplementedException('oops! this feature is yet to be implemented');
    }

    public static function count(string $table, $column = "*")
    {
        return static::_count($table, $column);
    }

    public static function _count(string $table, $column = "*", $reset_instance = true)
    {
        if (! self::$instantiated)
            static::init();
        $query_arr = static::$bind_or_filter === null ? [] : static::$bind_or_filter;

        $i = 0;
        // foreach ($keys as $key) {
        //     $query_arr[$key] = $values[$i];
        //     $i++;
        // }

        if (!$count = mysqly::count($table, $query_arr, static::$operators, static::$or_ands)) {
            if ($reset_instance)
                static::resetInstance();
            return false;
        }
        if ($reset_instance)
            static::resetInstance();

        return $count;
    }

    public static function avg($column)
    {
        throw new NotImplementedException('oops! this feature is yet to be implemented');
    }

    public static function max($column)
    {
        throw new NotImplementedException('oops! this feature is yet to be implemented');
    }
    
    public static function min($column)
    {
        throw new NotImplementedException('oops! this feature is yet to be implemented');
    }

    public static function update(string $table, $values, $id)
    {
        return static::_update($table, $values, $id);
    }

    public static function delete(string $table, $id)
    {
        if (! self::$instantiated)
            static::init();
        $query_arr = static::$bind_or_filter === null ? [] : static::$bind_or_filter;

        if ($id !== 0 && count($query_arr) < 1)
            $query_arr['id'] = $id;

        $val = mysqly::remove($table, $query_arr, static::$operators, static::$or_ands);
        static::resetInstance();
        return $val;
    }

    public static function restore(string $table, $id)
    {
        // if (!static::$child->softdeletes) {
        //     throw new Exception("this model does not support soft deleting");
        // }

        return static::_update($table, ['deleted_at', null], $id);
    }

    public static function limit($amount)
    {
        static::$bind_or_filter['LIMIT'] = $amount;
        return new static;
    }

    public static function offset($postion)
    {
        static::$bind_or_filter['OFFSET'] = $postion;
        return new static;
    }

    public static function where($column, $operatorOrValueOrMethod = null, $value = null)
    {
        return static::_where($column, $operatorOrValueOrMethod, $value, 'AND');
    }
    
    public static function whereLike($column, $value = null)
    {
        return static::_where($column, 'LIKE', $value, 'AND');
    }

    public static function whereNotLike($column, $value = null)
    {
        return static::_where($column, 'NOT LIKE', $value, 'AND');
    }

    public static function whereLessThan($column, $value = null)
    {
        return static::_where($column, '<', $value, 'AND');
    }

    public static function whereGreaterThan($column, $value = null)
    {
        return static::_where($column, '>', $value, 'AND');
    }

    public static function whereLessThanOrEqual($column, $value = null)
    {
        return static::_where($column, '<=', $value, 'AND');
    }

    public static function whereGreaterThanOrEqual($column, $value = null)
    {
        return static::_where($column, '>=', $value, 'AND');
    }

    public static function whereEqual($column, $value = null)
    {
        return static::_where($column, '=', $value, 'AND');
    }

    public static function whereNotEqual($column, $value = null)
    {
        return static::_where($column, '!=', $value, 'AND');
    }

    public static function orWhere($column, $operatorOrValue = null, $value = null)
    {
        return static::_where($column, $operatorOrValue, $value, 'OR');
    }

    public static function orWhereLike($column, $value = null)
    {
        return static::_where($column, 'LIKE', $value, 'OR');
    }

    public static function orWhereNotLike($column, $value = null)
    {
        return static::_where($column, 'NOT LIKE', $value, 'OR');
    }
    
    public static function orWhereLessThan($column, $value = null)
    {
        return static::_where($column, '<', $value, 'OR');
    }

    public static function orWhereGreaterThan($column, $value = null)
    {
        return static::_where($column, '>', $value, 'OR');
    }

    public static function orWhereLessThanOrEqual($column, $value = null)
    {
        return static::_where($column, '<=', $value, 'OR');
    }

    public static function orWhereGreaterThanOrEqual($column, $value = null)
    {
        return static::_where($column, '>=', $value, 'OR');
    }

    public static function orWhereEqual($column, $value = null)
    {
        return static::_where($column, '=', $value, 'OR');
    }

    public static function orWhereNotEqual($column, $value = null)
    {
        return static::_where($column, '!=', $value, 'OR');
    }

    /**
     * update a model
     * 
     * @param array $values
     * @param int $id
     * @param bool $internal
     * @return self|bool|array
     */
    private function _update(string $table, array $values, int $id, string|array $fields = '*')
    {   
        if (! self::$instantiated)
            static::init();
        $query_arr = static::$bind_or_filter === null ? [] : static::$bind_or_filter;

        $query_arr['id'] = $id;

        $count = mysqly::update($table, $query_arr, $values, static::$operators, static::$or_ands);

        if (!$model = mysqly::fetch($table, $query_arr, $fields, static::$operators, static::$or_ands)) {
            static::resetInstance();
            return false;
        }

        static::resetInstance();

        return $model[0];
    }

    private function _where(string $column, string $operatorOrValue = null, $value = null, $boolean = "AND")
    {
        if (! self::$instantiated)
            static::init();
        $bind_or_filter = static::$bind_or_filter;
        if ($bind_or_filter != null) {
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

        return new static;
    }

    public static function distinct($column)
    {
        is_string(static::$operators) ? static::$operators = ["DISTINCT `$column`"] : array_push(static::$operators, "DISTINCT `$column`");

        return new static;
    }
}