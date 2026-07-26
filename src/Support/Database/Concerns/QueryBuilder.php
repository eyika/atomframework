<?php

namespace Eyika\Atom\Framework\Support\Database\Concerns;

use Carbon\Carbon;
use Exception;
use Eyika\Atom\Framework\Support\Arr;
use Eyika\Atom\Framework\Support\Database\Model;
use Eyika\Atom\Framework\Support\Str;
use Eyika\Atom\Framework\Support\Database\PaginatedData;
use Eyika\Atom\Framework\Support\Facade\DatabaseConnection;

trait QueryBuilder
{
    use ModelHelpers;

    public static function getBuilder()
    {
        $classname = static::class;
        return new $classname;
    }

    /** Pessimistic write lock (SELECT ... FOR UPDATE) for the next first()/get(). */
    protected $for_update = false;

    /**
     * Add a row-level write lock to the next first()/get() read. Use inside a
     * transaction to serialize read-modify-write access to a row (e.g. a wallet
     * balance). Chainable; the flag is cleared by resetInstance() after the read.
     *
     * Underscore-prefixed + whitelisted in Model::DYNAMIC_STATIC_METHODS so both
     * the instance chain (getBuilder()->lockForUpdate()) and the static entry
     * (Model::lockForUpdate()) resolve through __call/__callStatic.
     */
    public function _lockForUpdate()
    {
        $this->for_update = true;
        return $this;
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
        $this->for_update = false;
        $this->transaction_mode = false;
        $this->with_model_name = '';
        $this->joins = [];
    }

    public function orderBy($column = "id", $direction = "ASC")
    {
        // SECURITY: escape each column identifier and whitelist the direction so a
        // user-supplied sort column/direction (e.g. from a query string) can't inject.
        $dir = strtoupper(trim((string) $direction)) === 'DESC' ? 'DESC' : 'ASC';
        $cols = array_map(
            fn($c) => \Eyika\Atom\Framework\Support\Database\Connection::quoteQualified(trim($c)),
            explode(',', (string) $column)
        );
        $this->bind_or_filter['ORDER BY'] = implode(', ', $cols) . " $dir";
        return $this;
    }

    public function fill($values, $returnInstance = false)
    {
        foreach ($values as $key => $value) {
            $value = $this->castAttribute($key, $value);
            if (Arr::exists($this::fillable, $key)) {
                $this->{$key} = $value;
                continue;
            }
            $this->dynamicProperties[$key] = $value;
        }
        if ($returnInstance)
            return clone $this;
    }

    /**
     * Cast an attribute to a native PHP type.
     *
     * @param string $key
     * @param mixed $value
     * @return mixed
     */
    protected function castAttribute(string $key, mixed $value): mixed
    {
        if (!defined('static::casts') || !array_key_exists($key, static::casts) || is_null($value)) {
            return $value;
        }

        switch (static::casts[$key]) {
            case 'bool':
            case 'boolean':
                return (bool)$value;
            case 'int':
            case 'integer':
                return (int)$value;
            case 'float':
            case 'double':
                return (float)$value;
            case 'string':
                return (string)$value;
            case 'array':
            case 'json':
                return is_string($value) ? json_decode($value, true) : (array)$value;
            case 'object':
                return is_string($value) ? json_decode($value, false) : (object)$value;
            default:
                return $value;
        }
    }

    public function toArray($guard = true, $select = [], $ignore = [], $includeDynamicProperties = false)
    {
        return $this->_toArray($guard, $select, $ignore, $includeDynamicProperties, false);
    }

    public function raw($sql, $bind)
    {
        return DatabaseConnection::exec($sql, $bind);
    }

    public function _create($values, $is_protected = true, $select = [])
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

    public function _find($id = 0, $is_protected = true)
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
    
        if (!$model = DatabaseConnection::fetch($this->table, $query_arr, $fields, $this->operators, $this->or_ands, $this->for_update, $this->joins)) {
            $this->resetInstance();
            return false;
        }
        $model = $model[0];
        $this->decryptValues($model);

        $this->fill($model);
    
        // Trigger "retrieved" event for the main model
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
                if (empty($model->{$key_name."_id"})) {
                    $model->{$with_model_name} = null;
                    continue;
                }
                $item = $model->{$with_model_name}();
                $model->{$with_model_name} = $item;
                $model->relationshipItems[] = $with_model_name;
            }
            return $models;
        }

        if (empty($models->{$key_name."_id"})) {
            $models->{$with_model_name} = null;
            return $models;
        }
        $item = $models->{$with_model_name}();
        $models->{$with_model_name} = $item;
        $models->relationshipItems[] = $with_model_name;
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

    public function _findOr($id = 0, $is_protected = true, $callable = null)
    {
        if (!$model = $this->find($id, $is_protected)) {
            $model = $callable($id, $is_protected);
        }

        return $model;
    }

    public function _first($is_protected = true)
    {
        return $this->find(is_protected: $is_protected);
    }

    /**
     * Return the first matching model, or the result of $callable when none is found
     * (mirrors _findOr but for the accumulated where-filter query).
     */
    public function _firstOr($callable = null, $is_protected = true)
    {
        if (!$model = $this->first($is_protected)) {
            return is_callable($callable) ? $callable() : $model;
        }

        return $model;
    }

    public function _firstWhere($column, $operatorOrValue = null, $value = null, $is_protected = true)
    {
        return $this->where($column, $operatorOrValue, $value)->first($is_protected);
    }

    public function _firstOrCreate($search, $keyvalues, $is_protected = true, $select = [])
    {
        if (!$model = $this->findByArray(array_keys($search), array_values($search), 'AND', $is_protected, $select)) {
            $model = $this->create($keyvalues, $is_protected, $select);
        }
        return $model;
    }

    /**
     * Return the first row matching $search, or a NEW UNSAVED instance filled with
     * $search + $values (Laravel-style). Previously took no args, ignored the search
     * and persisted via save().
     */
    public function _firstOrNew($search, $values = [], $is_protected = true)
    {
        if ($model = $this->findByArray(array_keys($search), array_values($search), 'AND', $is_protected)) {
            return $model;
        }

        $instance = new static();
        $instance->fill(array_merge($search, $values));
        return $instance;
    }

    public function _findBy($key, $value, $is_protected = true, $select = [])
    {
        $query_arr = $this->bind_or_filter === null ? [] : $this->bind_or_filter;

        $query_arr[$key] = $value;
        if ($this->softdeletes) {
            $query_arr['deleted_at'] = "IS NULL";
            is_string($this->or_ands) ? $this->or_ands = ["AND"] : array_push($this->or_ands, "AND");
        }
    
        $this->useHashForEncryptedColumnComparisonQueries($query_arr);

        if (count($select)) {
            $fields = $select;
        } else {
            $fields = $is_protected ? \array_diff($this::fillable, $this::guarded) : $this::fillable;
        }

        if (!$model = DatabaseConnection::fetch($this->table, $query_arr, $fields, $this->operators, $this->or_ands, $this->for_update, $this->joins)) {
            $this->resetInstance();
            return false;
        }
        $model = $model[0];
        $this->decryptValues($model);

        $this->fill($model);
    
        // Trigger "retrieved" event for the main model
        $this->boot($this, 'retrieved');
        $this->booted($this, 'retrieved');
        $this->booting($this, 'retrieved');
    
        return $this->fetchRelationship($this, true, $is_protected);
    }

    public function _findByArray($keys, $values, $or_and = "AND", $is_protected = true, $select = [])
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

        if (!$model = DatabaseConnection::fetch($this->table, $query_arr, $fields)) {
            return false;
        }

        $model = $model[0];
        $this->decryptValues($model);

        $this->fill($model);
    
        // Trigger "retrieved" event for the main model
        $this->boot($this, 'retrieved');
        $this->booted($this, 'retrieved');
        $this->booting($this, 'retrieved');
    
        return $this->fetchRelationship($this, true, $is_protected);
    }

    public function _all($is_protected = true, $select = [])
    {
        $query_arr = [];
        if ($this->bind_or_filter)
            $query_arr = $this->bind_or_filter;
    
        if ($this->softdeletes) {
            $query_arr['deleted_at'] = "IS NULL";
            is_array($this->or_ands) ? $this->or_ands[] = "AND" : $this->or_ands = ["AND"];
        }
    
        $this->useHashForEncryptedColumnComparisonQueries($query_arr);
        if (count($select)) {
            $fields = $select;
        } else {
            $fields = $is_protected ? \array_diff($this::fillable, $this::guarded) : $this::fillable;
        }
    
        if (!$models = DatabaseConnection::fetch($this->table, $query_arr, $fields, $this->operators, $this->or_ands, $this->for_update, $this->joins)) {
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

        return $this->fetchRelationship($models, true, is_protected: $is_protected);
    }

    public function with($models)
    {
        if (is_array($models))
            $this->with_model_names = empty($this->with_model_names) ? $models : Arr::merge($this->with_model_names, $models);
        else
            $this->with_model_names[] = $models;

        return $this;
    }

    public function _get($is_protected = true, $select = [])
    {
        return $this->all($is_protected, $select);
    }

    public function _paginate($currentPage = null, $recordsPerPage = null, $isProtected = true, $select = [], $routeName = null)
    {
        $currentPage = $currentPage ?? 1;
        $recordsPerPage = $recordsPerPage ?? $this->recordsPerPage;

        $totalRecords = $this->__aggregate($this->primaryKey, 'count', false);
        // Calculate total pages
        $totalPages = ceil($totalRecords / $recordsPerPage);
        // Calculate the offset
        $offset = ($currentPage - 1) * $recordsPerPage;

        $this->limit($recordsPerPage);
        $this->offset($offset);

        $data = $this->all($isProtected, $select);

        if (!$data) {
            return false;
        }
        return PaginatedData::init($data, $totalRecords, $recordsPerPage, $totalPages, $currentPage, $routeName);
    }

    public function _random()
    {
        $data = $this->__aggregate(method: 'random', reset_instance: false);

        return $this->fetchRelationship($data);
    }

    public function _count($column = "*")
    {
        if (!$dat = $this->__aggregate($column)) {
            return 0;
        }
        return $dat;
    }

    public function _avg($column)
    {
        if (!$dat = $this->__aggregate($column, 'avg')) {
            return 0;
        }
        return $dat;
    }

    public function _max($column)
    {
        if (!$dat = $this->__aggregate($column, 'max')) {
            return 0;
        }
        return $dat;
    }
    
    public function _min($column)
    {
        if (!$dat = $this->__aggregate($column, 'min')) {
            return 0;
        }
        return $dat;
    }

    public function _sum($column)
    {
        if (!$dat = $this->__aggregate($column, 'sum')) {
            return 0;
        }
        return $dat;
    }

    public function _group_concat($column)
    {
        if (!$dat = $this->__aggregate($column, 'group_concat')) {
            return '';
        }
        return $dat;
    }
    
    public function _var_pop($column)
    {
        if (!$dat = $this->__aggregate($column, 'var_pop')) {
            return 0;
        }
        return $dat;
    }

    public function _stddev($column)
    {
        if (!$dat = $this->__aggregate($column, 'stddev')) {
            return 0;
        }
        return $dat;
    }
    
    public function _bit_and($column)
    {
        if (!$dat = $this->__aggregate($column, 'bit_and')) {
            return 0;
        }
        return $dat;
    }

    public function _bit_or($column)
    {
        if (!$dat = $this->__aggregate($column, 'bit_or')) {
            return 0;
        }
        return $dat;
    }

    public function _bit_xor($column)
    {
        if (!$dat = $this->__aggregate($column, 'bit_xor')) {
            return 0;
        }
        return $dat;
    }

    public function __aggregate($column = "*", $method = 'count', $reset_instance = true)
    {
        $query_arr = $this->bind_or_filter === null ? [] : $this->bind_or_filter;

        if ($this->softdeletes) {
            $query_arr['deleted_at'] = "IS NULL";
            if (gettype($this->or_ands) === 'string' || empty($this->or_ands)) {
                $this->or_ands = Arr::wrap($this->or_ands);
            }
            array_push($this->or_ands, "AND");
        }

        if ($method == 'count' && $column != '*') {
            if (gettype($this->operators) === 'string' || empty($this->operators)) {
                $this->operators = Arr::wrap($this->operators);
            }
            $this->operators[] = "DISTINCT " . \Eyika\Atom\Framework\Support\Database\Connection::quoteQualified($column);
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

    public function _increment($column, $step = 1)
    {
        if ($this->bind_or_filter === null)
            $this->bind_or_filter['id'] = $this->{$this->primaryKey};
        $query_arr = $this->bind_or_filter;
        $column = $this->_parseColumn($column);

        if (!DatabaseConnection::increment($column, $this->table, $query_arr, $this->operators, $this->or_ands, $step)) {
            return false;
        }
        return true;
    }

    public function _decrement($column, $step = 1)
    {

        if ($this->bind_or_filter === null)
            $this->bind_or_filter['id'] = $this->{$this->primaryKey};

        $query_arr = $this->bind_or_filter;
        $column = $this->_parseColumn($column);

        if (!DatabaseConnection::decrement($column, $this->table, $query_arr, $this->operators, $this->or_ands, $step)) {
            return false;
        }
        return true;
    }

    public function _update($values, $id=0, $is_protected = true)
    {
        return $this->__update($values, $id, is_protected: $is_protected);
    }

    public function _updateOrCreate($values, $id=0, $is_protected = true)
    {
        return $this->__update($values, $id, is_protected: $is_protected, create_if_not_exist: true);
    }

    public function _delete($id = 0)
    {
        $id = $id > 0 ? $id : $this->{$this->primaryKey};
        
        $query_arr = $this->bind_or_filter === null ? [] : $this->bind_or_filter;

        $this->boot($this, 'deleting');
        $this->booted($this, 'deleting');
        $this->booting($this, 'deleting');

        if ((int) $id > 0 && count($query_arr) < 1)
            $query_arr['id'] = $id;

        // Refuse a filterless/idless delete — with no id and no where() it would
        // DELETE the ENTIRE table (or, with a null id, silently match nothing).
        if (count($query_arr) < 1) {
            throw new Exception('delete() requires an id or a where() filter; refusing to delete every row.');
        }

        $this->useHashForEncryptedColumnComparisonQueries($query_arr);

        if ($this->softdeletes) {
            $query_arr['deleted_at'] = "IS NULL";
            is_string($this->or_ands) ? $this->or_ands = ["AND"] : array_push($this->or_ands, "AND");
            DatabaseConnection::update($this->table, $query_arr, ['deleted_at' => "now"], $this->operators, $this->or_ands);
            
            $this->resetInstance();
            return true;
        }

        $val = DatabaseConnection::remove($this->table, $query_arr, $this->operators, $this->or_ands);

        $this->boot($this, 'deleted');
        $this->booted($this, 'deleted');
        $this->booting($this, 'deleted');
        $this->resetInstance();
        return $val;
    }

    public function _restore($id = 0)
    {
        if (!$this->softdeletes) {
            throw new Exception("this model does not support soft deleting");
        }
        $id = $id > 0 ? $id : $this->{$this->primaryKey};

        // Associative — a list ['deleted_at', null] wrote columns `0`/`1` and never
        // cleared deleted_at (corrupting the row instead of restoring it).
        return $this->__update(['deleted_at' => null], $id, true);
    }

    public function _limit($amount)
    {
        $this->bind_or_filter['LIMIT'] = (int) $amount;
        return $this;
    }

    public function _offset($postion)
    {
        $this->bind_or_filter['OFFSET'] = (int) $postion;
        return $this;
    }

    public function _where($column, $operatorOrValueOrMethod = null, $value = null)
    {
        return $this->__where($column, $operatorOrValueOrMethod, $value, 'AND');
    }
    
    public function _whereLike($column, $value)
    {
        return $this->__where($column, 'LIKE', $value, 'AND');
    }
    
    public function _whereIn($column, $values)
    {
        return $this->__where($column, 'IN', $values, 'AND');
    }
    
    public function _whereNotIn($column, $values)
    {
        return $this->__where($column, 'NOT IN', $values, 'AND');
    }

    public function _whereNotLike($column, $value)
    {
        return $this->__where($column, 'NOT LIKE', $value, 'AND');
    }

    public function _whereBetween($column, array $range)
    {
        if (count($range) !== 2) {
            throw new \InvalidArgumentException('whereBetween expects a [min, max] array');
        }
        return $this->__where($column, 'BETWEEN', array_values($range), 'AND');
    }

    public function _whereNotBetween($column, array $range)
    {
        if (count($range) !== 2) {
            throw new \InvalidArgumentException('whereNotBetween expects a [min, max] array');
        }
        return $this->__where($column, 'NOT BETWEEN', array_values($range), 'AND');
    }

    public function _whereLessThan($column, $value)
    {
        return $this->__where($column, '<', $value, 'AND');
    }

    public function _whereGreaterThan($column, $value)
    {
        return $this->__where($column, '>', $value, 'AND');
    }

    public function _whereLessThanOrEqual($column, $value)
    {
        return $this->__where($column, '<=', $value, 'AND');
    }

    public function _whereGreaterThanOrEqual($column, $value)
    {
        return $this->__where($column, '>=', $value, 'AND');
    }

    public function _whereEqual($column, $value)
    {
        return $this->__where($column, '=', $value, 'AND');
    }

    public function _whereNotEqual($column, $value)
    {
        return $this->__where($column, '!=', $value, 'AND');
    }

    public function _whereNull($column)
    {
        return $this->__where($column, 'IS NULL');
    }

    public function _whereNotNull($column)
    {
        return $this->__where($column, 'IS NOT NULL');
    }

    public function _orWhere($column, $operatorOrValue = null, $value = null)
    {
        return $this->__where($column, $operatorOrValue, $value, 'OR');
    }
    
    public function _orWhereIn($column, $values)
    {
        return $this->__where($column, 'IN', $values, 'OR');
    }
    
    public function _orWhereNotIn($column, $values)
    {
        return $this->__where($column, 'NOT IN', $values, 'OR');
    }

    public function _orWhereLike($column, $value)
    {
        return $this->__where($column, 'LIKE', $value, 'OR');
    }

    public function _orWhereNotLike($column, $value)
    {
        return $this->__where($column, 'NOT LIKE', $value, 'OR');
    }
    
    public function _orWhereLessThan($column, $value)
    {
        return $this->__where($column, '<', $value, 'OR');
    }

    public function _orWhereGreaterThan($column, $value)
    {
        return $this->__where($column, '>', $value, 'OR');
    }

    public function _orWhereLessThanOrEqual($column, $value)
    {
        return $this->__where($column, '<=', $value, 'OR');
    }

    public function _orWhereGreaterThanOrEqual($column, $value)
    {
        return $this->__where($column, '>=', $value, 'OR');
    }

    public function _orWhereEqual($column, $value)
    {
        return $this->__where($column, '=', $value, 'OR');
    }

    public function _orWhereNotEqual($column, $value)
    {
        return $this->__where($column, '!=', $value, 'OR');
    }

    public function _orWhereNull($column)
    {
        return $this->__where($column, ' IS NULL', boolean: 'OR');
    }

    public function _orWhereNotNull($column)
    {
        return $this->__where($column, 'NOT NULL', boolean: 'OR');
    }

    public function _beginTransaction()
    {
        DatabaseConnection::beginTransaction();
        $this->transaction_mode = true;
    }

    public function _commit()
    {
        DatabaseConnection::commit();
        $this->transaction_mode = false;
    }

    public function _rollback()
    {
        DatabaseConnection::rollback();
        $this->transaction_mode = false;
    }

    public function _distinct($column)
    {
        $distinct = "DISTINCT " . \Eyika\Atom\Framework\Support\Database\Connection::quoteQualified($column);
        is_string($this->operators) ? $this->operators = [$distinct] : array_push($this->operators, $distinct);
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
    private function __update(array $values, int $id=0, $internal = false, $is_protected = true, $should_fill = true, $create_if_not_exist = false)
    {
        $id = $id > 0 ? $id : $this->{$this->primaryKey};
        
        if ($this->bind_or_filter === null)
            $this->bind_or_filter['id'] = $id;
        $query_arr = $this->bind_or_filter === null ? [] : $this->bind_or_filter;

        if ($this->softdeletes && !$internal) {
            $query_arr['deleted_at'] = array_key_exists('deleted_at', $values) && $values['deleted_at'] === null ? "IS NOT NULL" : "IS NULL";
            is_string($this->or_ands) ? $this->or_ands = ["AND"] : array_push($this->or_ands, "AND");
        }
        $fields = $is_protected ? \array_diff($this::fillable, $this::guarded) : $this::fillable;

        $this->useHashForEncryptedColumnComparisonQueries($query_arr);
        $this->createHashDuplicatesForCreateAndUpdateQueries($values);
        $this->encryptValues($values);
        $this->serializeCastedValues($values);

        $create = $create_if_not_exist && !DatabaseConnection::fetch($this->table, $query_arr, $fields, $this->operators, $this->or_ands);
        if ($create) {
            $this->boot($this, 'creating');
            $this->booted($this, 'creating');
            $this->booting($this, 'creating');

            $values = array_merge($values, Arr::except($query_arr, array_merge(array_keys($values), ['updated_at', 'created_at', 'deleted_at'])));

            DatabaseConnection::insert($this->table, $values);
        } else {
            $this->boot($this, 'saving');
            $this->booted($this, 'saving');
            $this->booting($this, 'saving');

            $count = DatabaseConnection::update($this->table, $query_arr, $values, $this->operators, $this->or_ands);
        }

        if (!$model = DatabaseConnection::fetch($this->table, $query_arr, $fields, $this->operators, $this->or_ands, $this->for_update, $this->joins)) {
            $this->resetInstance();
            return false;
        }

        $model = $model[0];
        $this->decryptValues($model);
        $this->resetInstance();

        $model = $this->fill($model, true);

        if ($create) {
            $this->boot($model, 'created');
            $this->booted($model, 'created');
            $this->booting($model, 'created');
        } else {
            $this->boot($model, 'saved');
            $this->booted($model, 'saved');
            $this->booting($model, 'saved');
        }

        return $should_fill ? $model : $model->toArray($is_protected);
    }

    private function __where(string $column, string|null $operatorOrValue = null, $value = null, $boolean = "AND")
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
        $column = $this->_parseColumn($column);

        // Multiple where() calls on the same column must not overwrite each
        // other — e.g. ->where('created_at', '>=', $a)->where('created_at', '<=', $b).
        // We keep the real column in the key but append a __dupN suffix to
        // distinguish. Connection::condition() strips the suffix when building
        // the SQL column reference, keeping it only for the bind param name.
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

    private function __join(string $table, string $first, string $operator, string $second, string $type = 'INNER'): static
    {
        $this->joins[] = compact('type', 'table', 'first', 'operator', 'second');
        return $this;
    }

    public function _join($table, $first, $operator, $second)
    {
        return $this->__join($table, $first, $operator, $second);
    }

    public function _leftJoin($table, $first, $operator, $second)
    {
        return $this->__join($table, $first, $operator, $second, 'LEFT');
    }

    public function _rightJoin($table, $first, $operator, $second)
    {
        return $this->__join($table, $first, $operator, $second, 'RIGHT');
    }

    public function _fullOuterJoin($table, $first, $operator, $second)
    {
        return $this->__join($table, $first, $operator, $second, 'FULL OUTER');
    }

    private function _save($is_protected = true, $select = []): bool|self
    {
        if ($this->isSaved()) {
            $values = Arr::where($this->toArray(false, ignore: ['deleted_at', 'created_at', $this->primaryKey]), function ($v, $k) {      // to be used to filter out empty values in future
                return true;
            }, ARRAY_FILTER_USE_BOTH);

            if (array_key_exists('updated_at', $values) && empty($values['updated_at']))
                $values['updated_at'] = Carbon::now();

            $model = $this->__update($values, $this->{$this->primaryKey}, true, should_fill: false);
            if (!$model)
                return false;

            // __update(should_fill:false) returns a FLAT assoc array (toArray()).
            // Index it by the column NAME (not `[0]` / the property value) and keep
            // the current value if absent — the old code nulled updated_at + the PK
            // after every save.
            $this->{$this::UPDATED_AT} = $model[$this::UPDATED_AT] ?? $this->{$this::UPDATED_AT};
            $this->{$this->primaryKey} = $model[$this->primaryKey] ?? $this->{$this->primaryKey};

            return $this;
        }

        $this->boot($this, 'creating');
        $this->booted($this, 'creating');
        $this->booting($this, 'creating');

        $values = Arr::where($this->_toArray(false, ignore: ['deleted_at', $this->primaryKey]), function ($v, $k) {      // to be used to filter out empty values in future
            return true;
        }, ARRAY_FILTER_USE_BOTH);

        $timestamps = ['created_at', 'updated_at'];

        foreach ($timestamps as $timestamp) {
            if (array_key_exists($timestamp, $values) && empty($values[$timestamp]))
                $values[$timestamp] = Carbon::now();
        }

        $this->createHashDuplicatesForCreateAndUpdateQueries($values);
        $this->encryptValues($values);
        $this->serializeCastedValues($values);

        if (!$id = DatabaseConnection::insert($this->table, $values)) {
            return false;
        }

        if (count($select)) {
            $fields = $select;
        } else {
            $fields = $is_protected ? \array_diff($this::fillable, $this::guarded) : $this::fillable;
        }

        if (!$model = DatabaseConnection::fetch($this->table, ['id' => $id], $fields)) {
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

    private function _parseColumn(string $column): string
    {
        if (strpos($column, '.') !== false) {
            [$relation, $field] = explode('.', $column, 2);
            $relation_plural = Str::plural($relation);

            // Example: posts.user_id = users.id
            // a relation map might be better here for flexibility
            $this->_leftJoin($relation_plural, "{$this->table}.{$relation}_id", '=', $relation_plural.".id");

            return "$relation_plural.{$field}";
        }

        return "{$this->table}.{$column}";
    }

    private function _toArray($guard = true, $select = [], $ignore = [], $includeDynamicProperties = false, $ignore_null = true)
    {
        $result = array();

        $ignore = array_merge($ignore, [
            'fillable', 'guarded', 'table', 'primaryKey', 'exists', 'db', 'builder', 'dynamicProperties',
            'DatabaseConnection', 'keyType', 'incrementing', 'perPage', 'wasRecentlyCreated', 'child'
        ]);

        $obj_props = array_diff(array_keys(get_object_vars($this)), $ignore);
        $select = array_intersect($obj_props, $select);
        if (count($select)) {
            foreach ($select as $item) {
                $result[$item] = $this->{$item};
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
