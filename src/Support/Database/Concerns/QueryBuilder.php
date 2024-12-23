<?php

namespace Eyika\Atom\Framework\Support\Database\Concerns;

use Carbon\Carbon;
use Exception;
use Eyika\Atom\Framework\Exceptions\NotImplementedException;
use Eyika\Atom\Framework\Support\Arr;
use Eyika\Atom\Framework\Support\Database\mysqly;
use Eyika\Atom\Framework\Support\Database\PaginatedData;

use function Symfony\Component\Clock\now;

trait QueryBuilder
{
    use ModelHelpers;

    public static function getBuilder()
    {
        $classname = static::class;
        return new $classname;
    }

    private function prepareModel($values = [])
    {
        $this->or_ands = 'AND';
        $this->operators = '=';
        if (count($values))
            $this->child->fill($values);
    }

    protected function resetInstance()
    {
        $this->bind_or_filter = null;
        $this->or_ands = '';
        $this->operators = '=';
        $this->order = '';
        $this->transaction_mode = false;
    }

    public function orderBy($column = "id", $direction = "ASC")
    {
        $this->order = "$column $direction";
        return $this;
    }

    public function fill($values)
    {
        foreach ($this->child::fillable as $item) {
            if (Arr::keyExists($values, $item)) {
                $this->child->{$item} = $values[$item];
            }
        }
        return $this->child;
    }

    public function toArray($guard = true, $select = [], $ignore = [])
    {
        $result = array();

        $obj_props = array_diff(array_keys(get_object_vars($this->child)), [
            'fillable', 'guarded', 'table', 'primaryKey', 'exists', 'db', 'builder', 'dynamicProperties',
            'connection', 'keyType', 'incrementing', 'perPage', 'wasRecentlyCreated', 'child'
        ]);
        if (count($select)) {
            foreach ($select as $item) {
                if (Arr::exists($obj_props, $item)) {
                    $result[$item] = $this->child->{$item};
                }
            }
            return $result;
        }
        $items = $guard ? array_diff($this->child::fillable, array_merge($this->child::guarded, $ignore)) : array_diff($this->child::fillable, $ignore);
        foreach ($items as $item) {
            if (Arr::exists($obj_props, $item)) {
                $result[$item] = $this->child->{$item};
            }
        }

        return $result;
    }

    public function raw($sql, $bind)
    {
        return mysqly::exec($sql, $bind);
    }

    public function create($values, $is_protected = true, $select = [])
    {
        $this->child->fill($values);

        return $this->_save($is_protected, $select);
    }

    public function save()
    {
        if (!$this->_save())
            return false;

        return true;
    }

    public function find($id = 0, $is_protected = true)
    {
        $query_arr = [];
        if ($id === 0 && isset($this->child->{$this->child->primaryKey})) {
            $id = $this->child->{$this->child->primaryKey};
        }
        if ($this->bind_or_filter)
            $query_arr = $this->bind_or_filter;

        
        if ($id > 0)
            $query_arr['id'] = $id;
        if ($this->child->softdeletes) {
            $query_arr['deleted_at'] = "IS NULL";
            is_string($this->or_ands) ? $this->or_ands = ["AND"] : array_push($this->or_ands, "AND");
        }
        
        $fields = $is_protected ? \array_diff($this::fillable, $this::guarded) : $this::fillable;

        if (!$model = mysqly::fetch($this->table, $query_arr, $fields, $this->operators, $this->or_ands)) {
            $this->resetInstance();
            return false;
        }
        $this->resetInstance();
        return $this->fill($model[0]);
    }

    public function findOr($id = 0, $is_protected = true, $callable = null)
    {
        throw new NotImplementedException('oops! this feature is yet to be implemented');
    }

    public function first($is_protected = true)
    {
        return $this->find(is_protected: $is_protected);
    }

    public function firstWhere($column, $operatorOrValue = null, $value = null, $is_protected = true)
    {
        throw new NotImplementedException('oops! this feature is yet to be implemented');
    }

    public function firstOrCreate($search, $keyvalues, $is_protected = true, $select = [])
    {
        throw new NotImplementedException('oops! this feature is yet to be implemented');
    }

    public function firstOrNew($search, $keyvalues, $is_protected = true, $select = [])
    {
        throw new NotImplementedException('oops! this feature is yet to be implemented');
    }

    public function findBy($key, $value, $is_protected = true, $select = [])
    {
        $query_arr = $this->bind_or_filter === null ? [] : $this->bind_or_filter;

        $query_arr[$key] = $value;
        if ($this->child->softdeletes) {
            $query_arr['deleted_at'] = "IS NULL";
            is_string($this->or_ands) ? $this->or_ands = ["AND"] : array_push($this->or_ands, "AND");
        }
        if ($this->order !== "")
            $query_arr['order_by'] = $this->order;
    
        if (count($select)) {
            $fields = $select;
        } else {
            $fields = $is_protected ? \array_diff($this::fillable, $this::guarded) : $this::fillable;
        }

        if (!$model = mysqly::fetch($this->table, $query_arr, $fields, $this->operators, $this->or_ands)) {
            $this->resetInstance();
            return false;
        }
        $this->resetInstance();
        return $model;
    }

    public function findByArray($keys, $values, $or_and = "AND", $is_protected = true, $select = [])
    {
        if (count($keys) !== count($values)) {
            return false;
        }

        $query_arr = [];

        foreach ($keys as $pos => $key) {
            $query_arr[$key] = $values[$pos];
            is_string($this->or_ands) ? $this->or_ands = [$or_and] : array_push($this->or_ands, $or_and);
        }
        if ($this->child->softdeletes) {
            $query_arr['deleted_at'] = "IS NULL";
            array_push($this->or_ands, "AND");
        }
        
        if (count($select)) {
            $fields = $select;
        } else {
            $fields = $is_protected ? \array_diff($this::fillable, $this::guarded) : $this::fillable;
        }
        if (!$fields = mysqly::fetch($this->table, $query_arr, $fields)) {
            return false;
        }
        return $fields;
    }

    public function all($is_protected = true, $select = [])
    {
        $query_arr = [];
        if ($this->bind_or_filter)
            $query_arr = $this->bind_or_filter;

        if ($this->child->softdeletes) {
            $query_arr['deleted_at'] = "IS NULL";
            is_array($this->or_ands) ? $this->or_ands[] = "AND" : $this->or_ands = ["AND"];
        }
        if ($this->order !== "")
            $query_arr['order_by'] = $this->order;

        if (count($select)) {
            $fields = $select;
        } else {
            $fields = $is_protected ? \array_diff($this::fillable, $this::guarded) : $this::fillable;
        }
        if (!$fields = mysqly::fetch($this->table, $query_arr, $fields, $this->operators, $this->or_ands)) {
            $this->resetInstance();
            return false;
        }
        $this->resetInstance();
        return $fields;
    }

    public function with($model)
    {
        throw new NotImplementedException('method not fully implemented');
        $this->with_model_name = $model;
    }

    public function get($is_protected = true, $select = [])
    {
        return $this->all($is_protected, $select);
    }

    public function paginate($currentPage = null, $recordsPerPage = null, $is_protected = true, $select = [])
    {
        $currentPage = $currentPage ?? 1;
        $recordsPerPage = $recordsPerPage ?? $this->recordsPerPage;

        $totalRecords = $this->_count($this->child->primaryKey, false);
        // Calculate total pages
        $totalPages = ceil($totalRecords / $recordsPerPage);
        // Calculate the offset
        $offset = ($currentPage - 1) * $recordsPerPage;

        $this->limit($recordsPerPage);
        $this->offset($offset);

        $data = $this->all($is_protected, $select);

        if (!$data) {
            return false;
        }
        return PaginatedData::init($data, $totalRecords, $recordsPerPage, $totalPages, $currentPage);
    }

    public function random()
    {
        throw new NotImplementedException('oops! this feature is yet to be implemented');
    }

    public function count($column = "*")
    {
        return $this->_count($column);
    }

    public function _count($column = "*", $reset_instance = true)
    {
        $query_arr = $this->bind_or_filter === null ? [] : $this->bind_or_filter;

        $i = 0;
        // foreach ($keys as $key) {
        //     $query_arr[$key] = $values[$i];
        //     $i++;
        // }
        if ($this->child->softdeletes) {
            $query_arr['deleted_at'] = "IS NULL";
            is_string($this->or_ands) ? $this->or_ands = ["AND"] : array_push($this->or_ands, "AND");
        }

        if (!$count = mysqly::count($this->table, $query_arr, $this->operators, $this->or_ands)) {
            if ($reset_instance)
                $this->resetInstance();
            return false;
        }
        if ($reset_instance)
            $this->resetInstance();

        return $count;
    }

    public function avg($column)
    {
        throw new NotImplementedException('oops! this feature is yet to be implemented');
    }

    public function max($column)
    {
        throw new NotImplementedException('oops! this feature is yet to be implemented');
    }
    
    public function min($column)
    {
        throw new NotImplementedException('oops! this feature is yet to be implemented');
    }

    public function update($values, $id=0, $is_protected = true)
    {
        return $this->_update($values, $id, is_protected: $is_protected);
    }

    public function delete($id = 0)
    {
        $id = $id > 0 ? $id : $this->child->{$this->child->primaryKey};
        
        $query_arr = $this->bind_or_filter === null ? [] : $this->bind_or_filter;

        if ($id !== 0 && count($query_arr) < 1)
            $query_arr['id'] = $id;
        if ($this->child->softdeletes) {
            $query_arr['deleted_at'] = "IS NULL";
            is_string($this->or_ands) ? $this->or_ands = ["AND"] : array_push($this->or_ands, "AND");
            mysqly::update($this->table, $query_arr, ['deleted_at' => "now"], $this->operators, $this->or_ands);
            
            $this->resetInstance();
            return true;
        }

        $val = mysqly::remove($this->table, $query_arr, $this->operators, $this->or_ands);
        $this->resetInstance();
        return $val;
    }

    public function restore($id = 0)
    {
        if (!$this->child->softdeletes) {
            throw new Exception("this model does not support soft deleting");
        }
        $id = $id > 0 ? $id : $this->child->{$this->child->primaryKey};

        return $this->_update(['deleted_at', null], $id, true);
    }

    public function limit($amount)
    {
        $this->bind_or_filter['LIMIT'] = $amount;
        return $this;
    }

    public function offset($postion)
    {
        $this->bind_or_filter['OFFSET'] = $postion;
        return $this;
    }

    public function where($column, $operatorOrValueOrMethod = null, $value = null)
    {
        return $this->_where($column, $operatorOrValueOrMethod, $value, 'AND');
    }
    
    public function whereLike($column, $value)
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

    public function whereNotLike($column, $value)
    {
        return $this->_where($column, 'NOT LIKE', $value, 'AND');
    }

    public function whereLessThan($column, $value)
    {
        return $this->_where($column, '<', $value, 'AND');
    }

    public function whereGreaterThan($column, $value)
    {
        return $this->_where($column, '>', $value, 'AND');
    }

    public function whereLessThanOrEqual($column, $value)
    {
        return $this->_where($column, '<=', $value, 'AND');
    }

    public function whereGreaterThanOrEqual($column, $value)
    {
        return $this->_where($column, '>=', $value, 'AND');
    }

    public function whereEqual($column, $value)
    {
        return $this->_where($column, '=', $value, 'AND');
    }

    public function whereNotEqual($column, $value)
    {
        return $this->_where($column, '!=', $value, 'AND');
    }

    public function whereNull($column)
    {
        return $this->_where($column, 'IS NULL');
    }

    public function whereNotNull($column)
    {
        return $this->_where($column, 'NOT NULL');
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

    public function orWhereLike($column, $value)
    {
        return $this->_where($column, 'LIKE', $value, 'OR');
    }

    public function orWhereNotLike($column, $value)
    {
        return $this->_where($column, 'NOT LIKE', $value, 'OR');
    }
    
    public function orWhereLessThan($column, $value)
    {
        return $this->_where($column, '<', $value, 'OR');
    }

    public function orWhereGreaterThan($column, $value)
    {
        return $this->_where($column, '>', $value, 'OR');
    }

    public function orWhereLessThanOrEqual($column, $value)
    {
        return $this->_where($column, '<=', $value, 'OR');
    }

    public function orWhereGreaterThanOrEqual($column, $value)
    {
        return $this->_where($column, '>=', $value, 'OR');
    }

    public function orWhereEqual($column, $value)
    {
        return $this->_where($column, '=', $value, 'OR');
    }

    public function orWhereNotEqual($column, $value)
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

    public function beginTransaction()
    {
        mysqly::beginTransaction();
        $this->transaction_mode = true;
    }

    public function commit()
    {
        mysqly::commit();
        $this->transaction_mode = false;
    }

    public function rollback()
    {
        mysqly::rollback();
        $this->transaction_mode = false;
    }

    public function distinct($column)
    {
        is_string($this->operators) ? $this->operators = ["DISTINCT `$column`"] : array_push($this->operators, "DISTINCT `$column`");
    }

    /**
     * update a model
     * 
     * @param array $values
     * @param int $id
     * @param bool $internal
     * @return self|bool|array
     */
    private function _update(array $values, int $id=0, $internal = false, $is_protected = true, $should_fill = true)
    {
        $id = $id > 0 ? $id : $this->child->{$this->child->primaryKey};
        
        $query_arr = $this->bind_or_filter === null ? [] : $this->bind_or_filter;

        $query_arr['id'] = $id;
        if ($this->child->softdeletes && !$internal) {
            $query_arr['deleted_at'] = "IS NULL";
            is_string($this->or_ands) ? $this->or_ands = ["AND"] : array_push($this->or_ands, "AND");
        }

        $count = mysqly::update($this->table, $query_arr, $values, $this->operators, $this->or_ands);

        $fields = $is_protected ? \array_diff($this::fillable, $this::guarded) : $this::fillable;
        if (!$model = mysqly::fetch($this->table, $query_arr, $fields, $this->operators, $this->or_ands)) {
            $this->resetInstance();
            return false;
        }

        $this->resetInstance();
        if ($should_fill)
            return $this->fill($model[0]);

        return $model[0];
    }

    private function _where(string $column, string|null $operatorOrValue = null, $value = null, $boolean = "AND")
    {
        $bind_or_filter = $this->bind_or_filter;
        if ($bind_or_filter != null) {
            foreach ($bind_or_filter as $key => $_value) {
                if (($key == 'LIMIT' || $key == 'OFFSET') && gettype($_value) == 'integer') {
                    throw new Exception("all where queries should come before $key queries");
                }
            }
        }
        if (is_null($value) && !is_null($operatorOrValue) && str_contains($operatorOrValue, ' NULL')) {// only column and value was given but value is like `IS NULL` or `NOT NULL`
            is_string($this->operators) ? $this->operators = [$operatorOrValue] : array_push($this->operators, $operatorOrValue);
        }
        else if (is_null($value) && !is_null($operatorOrValue) && !str_contains($operatorOrValue, ' NULL')) {// only column and value was given
            is_string($this->operators) ? $this->operators = ['='] : array_push($this->operators, '=');
            $value = $operatorOrValue;
        } else {
            is_string($this->operators) ? $this->operators = [$operatorOrValue] : array_push($this->operators, $operatorOrValue);
        }

        is_string($this->or_ands) ? $this->or_ands = [$boolean] : array_push($this->or_ands, $boolean);
        is_null($this->bind_or_filter) ? $this->bind_or_filter = array($column => $value) : $this->bind_or_filter[$column] = $value;

        return $this;
    }

    private function _save($is_protected = true, $select = []): bool|\Eyika\Atom\Framework\Support\Database\Model
    {
        if ($this->isSaved()) {
            $this->child->boot($this->child, 'saving');
            $this->child->booted($this->child, 'saving');
            $this->child->booting($this->child, 'saving');

            $values = Arr::where($this->child->toArray(false, ignore: ['deleted_at', 'created_at']), function ($v, $k) {      // to be used to filter out empty values in future
                return true;
            }, ARRAY_FILTER_USE_BOTH);

            if (array_key_exists('updated_at', $values) && empty($values['updated_at']))
                $values['updated_at'] = Carbon::now();

            $model = $this->_update($values, $this->child->{$this->child->primaryKey}, true, should_fill: false);
            if (!$model)
                return false;

            $this->child->{$this->child::UPDATED_AT} = $model[0][$this->child->{$this->child::UPDATED_AT}] ?? null;
            $this->child->{$this->child->primaryKey} = $model[0][$this->child->{$this->child->primaryKey}] ?? null;

            $this->child->boot($this->child, 'saved');
            $this->child->booted($this->child, 'saved');
            $this->child->booting($this->child, 'saved');
        } else {
            $this->child->boot($this->child, 'creating');
            $this->child->booted($this->child, 'creating');
            $this->child->booting($this->child, 'creating');

            $values = Arr::where($this->child->toArray(false, ignore: ['deleted_at']), function ($v, $k) {      // to be used to filter out empty values in future
                return true;
            }, ARRAY_FILTER_USE_BOTH);

            $timestamps = ['created_at', 'updated_at'];
    
            foreach ($timestamps as $timestamp) {
                if (array_key_exists($timestamp, $values) && empty($values[$timestamp]))
                    $values[$timestamp] = Carbon::now();
            }
    
            if (!$id = mysqly::insert($this->table, $values)) {
                return false;
            }

            if (count($select)) {
                $fields = $select;
            } else {
                $fields = $is_protected ? \array_diff($this::fillable, $this::guarded) : $this::fillable;
            }
    
            if (!$model = mysqly::fetch($this->table, ['id' => $id], $fields)) {
                return true;
            }
            $this->child->fill($model[0]);
    
            $this->child->boot($this->child, 'created');
            $this->child->booted($this->child, 'created');
            $this->child->booting($this->child, 'created');
    
            return $this->child;
        }
    }
}
