<?php

namespace Eyika\Atom\Framework\Support\Database\Concerns;

use Carbon\Carbon;
use Exception;
use Eyika\Atom\Framework\Support\Arr;
use Eyika\Atom\Framework\Support\Database\Model;
use Eyika\Atom\Framework\Support\Str;
use Eyika\Atom\Framework\Support\Database\mysqly;
use Eyika\Atom\Framework\Support\Database\PaginatedData;

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
            $this->fill($values);
    }

    protected function resetInstance()
    {
        $this->bind_or_filter = null;
        $this->or_ands = '';
        $this->operators = '=';
        $this->order = '';
        $this->transaction_mode = false;
        $this->with_model_name = '';
    }

    public function orderBy($column = "id", $direction = "ASC")
    {
        $this->order = "$column $direction";
        return $this;
    }

    public function fill($values, $returnInstance = false)
    {
        // foreach ($this::fillable as $item) {
        //     if (Arr::keyExists($values, $item)) {
        //         $this->{$item} = $values[$item];
        //     }
        // }
        foreach ($values as $key => $value) {
            if (Arr::exists($this::fillable, $key)) {
                $this->{$key} = $value;
                continue;
            }
            $this->dynamicProperties[$key] = $value;
        }
        if ($returnInstance)
            return clone $this;
    }

    public function toArray($guard = true, $select = [], $ignore = [], $includeDynamicProperties = false)
    {
        return $this->_toArray($guard, $select, $ignore, $includeDynamicProperties, false);
    }

    public function raw($sql, $bind)
    {
        return mysqly::exec($sql, $bind);
    }

    public function create($values, $is_protected = true, $select = [])
    {
        $this->fill($values);

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
        if ($id === 0 && isset($this->{$this->primaryKey})) {
            $id = $this->{$this->primaryKey};
        }
        if ($this->bind_or_filter)
            $query_arr = $this->bind_or_filter;
    
        if ($id > 0)
            $query_arr['id'] = $id;
        if ($this->softdeletes) {
            $query_arr['deleted_at'] = "IS NULL";
            is_string($this->or_ands) ? $this->or_ands = ["AND"] : array_push($this->or_ands, "AND");
        }
        $this->useHashForEncryptedColumnComparisonQueries($query_arr);
    
        $fields = $is_protected ? \array_diff($this::fillable, $this::guarded) : $this::fillable;
    
        if (!$model = mysqly::fetch($this->table, $query_arr, $fields, $this->operators, $this->or_ands)) {
            $this->resetInstance();
            return false;
        }
        $model = $model[0];
        $this->decryptValues($model);

        $this->fill($model);
    
        // 🔹 Trigger "retrieved" event for the main model
        $this->boot($this, 'retrieved');
        $this->booted($this, 'retrieved');
        $this->booting($this, 'retrieved');
    
        return $this->fetchRelationship($this, true, $is_protected);
    }
    
    /**
     * @param Model|Model[] $models
     * @param string $with_model_name
     * @param bool $is_protected
     */
    private function processRelationship ($models, string $with_model_name, bool $is_protected)
    {
        $key_name = Str::snake($with_model_name);

        if (is_array($models)) {
            foreach ($models as &$model) {
                // $this->{$key_name."_id"} = $model->{$key_name."_id"};

                if (empty($model->{$key_name."_id"})) {
                    $model->{$with_model_name} = null;
                    continue;
                }
                $item = $model->{$with_model_name}();
                $model->{$with_model_name} = $item;
                $model->relationshipItems[] = $with_model_name;
                // $model->{$with_model_name} = $item ? $item->toArray($is_protected) : $item;
            }
            return $models;
        }

        // $this->{$key_name."_id"} = $models->{$key_name."_id"};
        if (empty($models->{$key_name."_id"})) {
            $models->{$with_model_name} = null;
            return $models;
        }
        $item = $models->{$with_model_name}();
        $models->{$with_model_name} = $item;
        $models->relationshipItems[] = $with_model_name;
        // $models->{$with_model_name} = $item ? $item->toArray($is_protected) : $item;
        $this->resetInstance();

        return $models;
    }

    /**
     * @param Model|Model[] $model
     * @param bool $fill_models
     * @param bool $is_protected
     */
    private function fetchRelationship($model, $fill_models = false, $is_protected = true)
    {
        if (empty($this->with_model_names)) {
            $this->resetInstance();
            if ($fill_models)
                return $model;

            if (is_array($model)) {
                foreach ($model as &$_model) {
                    $_model = $_model->toArray($is_protected);
                }
                return $model;
            }
            return $model->toArray($is_protected);
        }

        // $is_array_of_model = is_array($model);
        /** @var string[] $with_model_names */
        $with_model_names = $this->with_model_names;
        $this->resetInstance();

        foreach ($with_model_names as $with_model_name) {
            if (method_exists($this, $with_model_name)) {
                $model = $this->processRelationship($model, $with_model_name, $is_protected);
                continue;
            }

            $plural_camel = Str::camel(Str::plural($with_model_name));
            if (method_exists($this, $plural_camel)) {
                $model = $this->processRelationship($model, $plural_camel, $is_protected);
                continue;
            }
            
            $plural_snake = Str::snake(Str::plural($with_model_name));
            if (method_exists($this, $plural_snake)) {
                $model = $this->processRelationship($model, $plural_snake, $is_protected);
                continue;
            }

            $singular_camel = Str::camel(Str::singular($with_model_name));
            if (method_exists($this, $singular_camel)) {
                $model = $this->processRelationship($model, $singular_camel, $is_protected);
                continue;
            }

            $singular_snake = Str::snake(Str::singular($with_model_name));
            if (method_exists($this, $singular_snake)) {
                $model = $this->processRelationship($model, $singular_snake, $is_protected);
                continue;
            }
            
            if (is_array($model)) {
                foreach ($model as $key => $field) {
                    $model[$key]->$with_model_name = null;
                }
            } else {
                $model->$with_model_name = null;
            }
            continue;
        }

        return $model;
    }

    public function findOr($id = 0, $is_protected = true, $callable = null)
    {
        if (!$model = $this->find($id, $is_protected)) {
            $model = $callable($id, $is_protected);
        }

        return $model;
    }

    public function first($is_protected = true)
    {
        return $this->find(is_protected: $is_protected);
    }

    public function firstWhere($column, $operatorOrValue = null, $value = null, $is_protected = true)
    {
        return $this->where($column, $operatorOrValue, $value)->first($is_protected);
    }

    public function firstOrCreate($search, $keyvalues, $is_protected = true, $select = [])
    {
        if (!$model = $this->findByArray(array_keys($search), array_values($search), 'AND', $is_protected, $select)) {
            $model = $this->create($keyvalues, $is_protected, $select);
        }
        return $model;
    }

    public function firstOrNew()
    {
        if ($this->isSaved()) {
            return $this;
        }
        return $this->save();
    }

    public function findBy($key, $value, $is_protected = true, $select = [])
    {
        $query_arr = $this->bind_or_filter === null ? [] : $this->bind_or_filter;

        $query_arr[$key] = $value;
        if ($this->softdeletes) {
            $query_arr['deleted_at'] = "IS NULL";
            is_string($this->or_ands) ? $this->or_ands = ["AND"] : array_push($this->or_ands, "AND");
        }
        if ($this->order !== "")
            $query_arr['order_by'] = $this->order;
    
        $this->useHashForEncryptedColumnComparisonQueries($query_arr);

        if (count($select)) {
            $fields = $select;
        } else {
            $fields = $is_protected ? \array_diff($this::fillable, $this::guarded) : $this::fillable;
        }

        if (!$model = mysqly::fetch($this->table, $query_arr, $fields, $this->operators, $this->or_ands)) {
            $this->resetInstance();
            return false;
        }
        $model = $model[0];
        $this->decryptValues($model);

        $this->fill($model);
    
        // 🔹 Trigger "retrieved" event for the main model
        $this->boot($this, 'retrieved');
        $this->booted($this, 'retrieved');
        $this->booting($this, 'retrieved');
    
        return $this->fetchRelationship($this, true, $is_protected);
        // return $this->fetchRelationship($model[0], true, $is_protected);
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
        if ($this->softdeletes) {
            $query_arr['deleted_at'] = "IS NULL";
            array_push($this->or_ands, "AND");
        }
        
        $this->useHashForEncryptedColumnComparisonQueries($query_arr);

        if (count($select)) {
            $fields = $select;
        } else {
            $fields = $is_protected ? \array_diff($this::fillable, $this::guarded) : $this::fillable;
        }

        if (!$model = mysqly::fetch($this->table, $query_arr, $fields)) {
            return false;
        }

        $model = $model[0];
        $this->decryptValues($model);

        $this->fill($model);
    
        // 🔹 Trigger "retrieved" event for the main model
        $this->boot($this, 'retrieved');
        $this->booted($this, 'retrieved');
        $this->booting($this, 'retrieved');
    
        return $this->fetchRelationship($this, true, $is_protected);
        // return $this->fetchRelationship($fields[0], true, $is_protected);
    }

    public function all($is_protected = true, $select = [])
    {
        $query_arr = [];
        if ($this->bind_or_filter)
            $query_arr = $this->bind_or_filter;
    
        if ($this->softdeletes) {
            $query_arr['deleted_at'] = "IS NULL";
            is_array($this->or_ands) ? $this->or_ands[] = "AND" : $this->or_ands = ["AND"];
        }
        if ($this->order !== "")
            $query_arr['order_by'] = $this->order;
    
        $this->useHashForEncryptedColumnComparisonQueries($query_arr);
        if (count($select)) {
            $fields = $select;
        } else {
            $fields = $is_protected ? \array_diff($this::fillable, $this::guarded) : $this::fillable;
        }
    
        if (!$models = mysqly::fetch($this->table, $query_arr, $fields, $this->operators, $this->or_ands)) {
            $this->resetInstance();
            return false;
        }
    
        foreach ($models as &$model) {
            $this->decryptValues($model);
            $model = $this->fill($model, true);
            $this->boot($model, 'retrieved');
            $this->booted($model, 'retrieved');
            $this->booting($model, 'retrieved');
        }

        return $this->fetchRelationship($models, is_protected: $is_protected);
    }

    public function with($models)
    {
        if (is_array($models))
            $this->with_model_names = empty($this->with_model_names) ? $models : Arr::merge($this->with_model_names, $models);
        else
            $this->with_model_names[] = $models;

        return $this;
    }

    public function get($is_protected = true, $select = [])
    {
        return $this->all($is_protected, $select);
    }

    public function paginate($currentPage = null, $recordsPerPage = null, $is_protected = true, $select = [])
    {
        $currentPage = $currentPage ?? 1;
        $recordsPerPage = $recordsPerPage ?? $this->recordsPerPage;

        $totalRecords = $this->_aggregate($this->primaryKey, 'count', false);
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
        $data = $this->_aggregate(method: 'random', reset_instance: false);

        return $this->fetchRelationship($data);
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

        if ($this->softdeletes) {
            $query_arr['deleted_at'] = "IS NULL";
            is_string($this->or_ands) ? $this->or_ands = ["AND"] : array_push($this->or_ands, "AND");
        }

        if ($method == 'count' && $column != '*') {
            $this->operators[] = "DISTINCT $column";
        }

        $method = $method == 'count' ? $method : $method."_".$column;

        if (!$aggregate = mysqly::{$method}($this->table, $query_arr, $this->operators, $this->or_ands)) {
            if ($reset_instance)
                $this->resetInstance();
            return false;
        }
        if ($reset_instance)
            $this->resetInstance();

        return $aggregate;
    }

    public function update($values, $id=0, $is_protected = true)
    {
        return $this->_update($values, $id, is_protected: $is_protected);
    }

    public function updateOrCreate($values, $id=0, $is_protected = true)
    {
        return $this->_update($values, $id, is_protected: $is_protected, create_if_not_exist: true);
    }

    public function delete($id = 0)
    {
        $id = $id > 0 ? $id : $this->{$this->primaryKey};
        
        $query_arr = $this->bind_or_filter === null ? [] : $this->bind_or_filter;

        $this->boot($this, 'deleting');
        $this->booted($this, 'deleting');
        $this->booting($this, 'deleting');

        if ($id !== 0 && count($query_arr) < 1)
            $query_arr['id'] = $id;

        $this->useHashForEncryptedColumnComparisonQueries($query_arr);

        if ($this->softdeletes) {
            $query_arr['deleted_at'] = "IS NULL";
            is_string($this->or_ands) ? $this->or_ands = ["AND"] : array_push($this->or_ands, "AND");
            mysqly::update($this->table, $query_arr, ['deleted_at' => "now"], $this->operators, $this->or_ands);
            
            $this->resetInstance();
            return true;
        }

        $val = mysqly::remove($this->table, $query_arr, $this->operators, $this->or_ands);

        $this->boot($this, 'deleted');
        $this->booted($this, 'deleted');
        $this->booting($this, 'deleted');
        $this->resetInstance();
        return $val;
    }

    public function restore($id = 0)
    {
        if (!$this->softdeletes) {
            throw new Exception("this model does not support soft deleting");
        }
        $id = $id > 0 ? $id : $this->{$this->primaryKey};

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
     * @param bool $is_protected
     * @param bool $should_fill
     * @param bool $create_if_not_exist
     * 
     * @return self|bool|array
     */
    private function _update(array $values, int $id=0, $internal = false, $is_protected = true, $should_fill = true, $create_if_not_exist = false)
    {
        $id = $id > 0 ? $id : $this->{$this->primaryKey};
        
        if ($this->bind_or_filter === null)
            $this->bind_or_filter['id'] = $id;
        $query_arr = $this->bind_or_filter === null ? [] : $this->bind_or_filter;

        if ($this->softdeletes && !$internal) {
            $query_arr['deleted_at'] = "IS NULL";
            is_string($this->or_ands) ? $this->or_ands = ["AND"] : array_push($this->or_ands, "AND");
        }
        $fields = $is_protected ? \array_diff($this::fillable, $this::guarded) : $this::fillable;

        $this->useHashForEncryptedColumnComparisonQueries($query_arr);
        $this->createHashDuplicatesForCreateAndUpdateQueries($values);
        $this->encryptValues($values);

        if ($create_if_not_exist && !mysqly::fetch($this->table, $query_arr, $fields, $this->operators, $this->or_ands)) {
            $this->boot($this, 'creating');
            $this->booted($this, 'creating');
            $this->booting($this, 'creating');

            mysqly::insert($this->table, $values);

            $this->boot($this, 'created');
            $this->booted($this, 'created');
            $this->booting($this, 'created');
        } else {
            $this->boot($this, 'saving');
            $this->booted($this, 'saving');
            $this->booting($this, 'saving');

            $count = mysqly::update($this->table, $query_arr, $values, $this->operators, $this->or_ands);

            $this->boot($this, 'saved');
            $this->booted($this, 'saved');
            $this->booting($this, 'saved');
        }

        if (!$model = mysqly::fetch($this->table, $query_arr, $fields, $this->operators, $this->or_ands)) {
            $this->resetInstance();
            return false;
        }
        $this->decryptValues($model);

        $this->resetInstance();
        if ($should_fill)
            return $this->fill($model[0], true);

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

    private function _save($is_protected = true, $select = []): bool|self
    {
        if ($this->isSaved()) {
            $values = Arr::where($this->toArray(false, ignore: ['deleted_at', 'created_at']), function ($v, $k) {      // to be used to filter out empty values in future
                return true;
            }, ARRAY_FILTER_USE_BOTH);

            if (array_key_exists('updated_at', $values) && empty($values['updated_at']))
                $values['updated_at'] = Carbon::now();

            $model = $this->_update($values, $this->{$this->primaryKey}, true, should_fill: false);
            if (!$model)
                return false;

            $this->{$this::UPDATED_AT} = $model[0][$this->{$this::UPDATED_AT}] ?? null;
            $this->{$this->primaryKey} = $model[0][$this->{$this->primaryKey}] ?? null;

            return $this;
        }

        $this->boot($this, 'creating');
        $this->booted($this, 'creating');
        $this->booting($this, 'creating');

        $values = Arr::where($this->_toArray(false, ignore: ['deleted_at']), function ($v, $k) {      // to be used to filter out empty values in future
            return true;
        }, ARRAY_FILTER_USE_BOTH);

        $timestamps = ['created_at', 'updated_at'];

        foreach ($timestamps as $timestamp) {
            if (array_key_exists($timestamp, $values) && empty($values[$timestamp]))
                $values[$timestamp] = Carbon::now();
        }

        $this->createHashDuplicatesForCreateAndUpdateQueries($values);
        $this->encryptValues($values);

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
        $model = $model[0];
        $this->decryptValues($model);
        $this->fill($model);

        $this->boot($this, 'created');
        $this->booted($this, 'created');
        $this->booting($this, 'created');

        return $this;
    }

    private function _toArray($guard = true, $select = [], $ignore = [], $includeDynamicProperties = false, $ignore_null = true)
    {
        $result = array();

        $ignore = array_merge($ignore, [
            'fillable', 'guarded', 'table', 'primaryKey', 'exists', 'db', 'builder', 'dynamicProperties',
            'connection', 'keyType', 'incrementing', 'perPage', 'wasRecentlyCreated', 'child'
        ]);

        $obj_props = array_diff(array_keys(get_object_vars($this)), $ignore);
        $select = array_intersect($obj_props, $select);
        if (count($select)) {
            foreach ($select as $item) {
                // if (Arr::exists($obj_props, $item)) {
                    $result[$item] = $this->{$item};
                // }
            }
            return $result;
        }

        $fillables = $includeDynamicProperties ? array_merge($this::fillable, $this->relationshipItems, array_keys($this->dynamicProperties)) : array_merge($this::fillable, $this->relationshipItems);

        $items = $guard ? array_diff($fillables, array_merge($this::guarded, $ignore)) : array_diff($fillables, $ignore);

        foreach ($items as $item) {
            if (!$ignore_null || ($ignore_null && !is_null($this->{$item})))
                $result[$item] = $this->{$item};
        }

        return $result;
    }
}
