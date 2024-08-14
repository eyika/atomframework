<?php

namespace Basttyy\FxDataServer\libs\Interfaces;

use Basttyy\FxDataServer\libs\PaginatedData;

interface ModelInterface extends ModelEventsInterface
{
    /**
     * The table associated with the model.
     *
     * @property string $table
     */

    /**
     * The primary key for the model in db
     *
     * @property string $primaryKey
     */

    /**
     * Wether the model can be soft deleted
     * 
     * @property string $softdeletes
     */

    /**
     * id property of the model
     * 
     * @property $id
     */

    /**
     * The "type" of the primary key ID.
     *
     * @property string $keyType
     */

    /**
     * Indicates what database attributes of the model can be filled at once
     * 
     * @var array $fillable
     */

    /**
     * Indicates what database attributes of the model can be exposed outside the application
     * 
     * @var array $guarded
     */

    /**
     * The name of the "created at" column.
     *
     * @var string|null $created_at
     */

    /**
     * The name of the "updated at" column.
     *
     * @var string|null $updated_at
     */

    /**
     * The placeholder for model dynamic properties
     * 
     * @var array $dynamicProperties;
     */

    /**
     * Get a new querybuilder instance of the called class
     * 
     * @return ModelInterface|ModelInterface&UserModelInterface
     */
    public static function getBuilder();
    /**
     * Order query by a culumn in a direction "ASC" or "DESC"
     * @param string $column
     * @param string $direction
     * 
     * @return self
     */
    public function orderBy($column = "id", $direction = "ASC");

    // public function addSelect()

    /**
     * Fill a model with array of values
     * @param array $values
     * 
     * @return self
     */
    public function fill($values);

    /**
     * Convert a model to key value pairs array
     * @param bool $guard 'wether to show or hide model guarded params'
     * @param array $select 'which parameters of the model to include, if given $guard will be ignored'
     * @param array $ignore 'which parameters to force ignore'
     * 
     * @return array
     */
    public function toArray($guard = true, $select = [], $ignore = []);

    /**
     * exec() General SQL query execution
     * 
     * @param string $sql
     * @param array $bind
     * 
     * @return \PDOStatement|false $statement
     */
    public function raw($sql, $bind);
    
    /**
     * create a model from array values and save to db
     * @param array $values
     * @param bool $is_protected 'wether to hide or show protected values'
     * @param array $select 'what parameters of model to fetch in results'
     * 
     * @return self|bool
     */
    public function create($values, $is_protected = true, $select = []);

    /**
     * save a model object to DB
     * 
     * @return bool
     */
    public function save();

    /**
     * Find a model by its id
     * @param int $id
     * @param bool $is_protected 'wether to hide or show protected values'
     * 
     * @return self|false
     */
    public function find($id = 0, $is_protected = true);

    /**
     * Find a model by its id, execute the closure if not found
     * @param int $id
     * @param bool $is_protected 'wether to hide or show protected values'
     * @param callable $callable
     * 
     * @return self|false
     */
    public function findOr($id = 0, $is_protected = true, $callable = null);

    /**
     * Alias of Find with no id provided
     * Retrieves the first of all results of a query
     * @param bool $is_protected 'wether to hide or show protected values'
     * 
     * @return self|false
     */
    public function first($is_protected = true);

    /**
     * Retrieves the first of all results of a query
     * No previous or subsequent where clause is required
     * @param string $column
     * @param string|null $operatorOrValue
     * @param mixed $value
     * @param bool $is_protected 'wether to hide or show protected values'
     * 
     * @return self|false
     */
    public function firstWhere($column, $operatorOrValue = null, $value = null, $is_protected = true);

    /**
     * Retrieve model by key value or create it if it doesn't exist from array values
     * search and keyvalues will be used together while creating the model
     * @param array $search
     * @param array $keyvalues
     * @param bool $is_protected 'wether to hide or show protected values'
     * @param array $select 'what parameters of model to fetch in results'
     * 
     * @return array|bool
     */
    public function firstOrCreate($search, $keyvalues, $is_protected = true, $select = []);

    /**
     * Retrieve model by key value or instantiate it if it doesn't exist from array values
     * The model still needs to be save to the DB by calling save()
     * search and keyvalues will be used together while creating the model
     * @param array $search
     * @param array $keyvalues
     * @param bool $is_protected 'wether to hide or show protected values'
     * @param array $select 'what parameters of model to fetch in results'
     * 
     * @return array|bool
     */
    public function firstOrNew($search, $keyvalues, $is_protected = true, $select = []);

    /**
     * Find a model by key and value
     * 
     * @param string $key
     * @param string $value
     * @param bool $is_protected 'wether to hide or show protected values'
     * @param array $select 'what parameters of model to fetch in results'
     * 
     * @return array|false
     */
    public function findBy($key, $value, $is_protected = true, $select = []);
    
    /**
     * Find a model by a set of keys and values
     * 
     * @param array $keys
     * @param array $values
     * @param string $or_and 'wether to use OR or AND to join where clauses'
     * @param bool $is_protected 'wether to hide or show protected values'
     * @param array $select 'what parameters of model to fetch in results'
     * 
     * @return array|false
     */
    public function findByArray($keys, $values, $or_and = "AND", $is_protected = true, $select = []);

    /**
     * Find all elements of a model
     * 
     * @param bool $is_protected 'wether to hide or show protected values'
     * @param array $select 'what parameters of model to fetch in results'
     * 
     * @return array|false
     */
    public function all($is_protected = true, $select = []);

    /**
     * Attach the related model to a model's query result
     * 
     * @param string $model
     * 
     * @return self
     */

    public function with($model);

    
    /**
     * Alias for all(), Find all elements of a model
     * 
     * @param bool $is_protected 'wether to hide or show protected values'
     * @param array $select 'what parameters of model to fetch in results'
     * 
     * @return array|false
     */
    public function get($is_protected = true, $select = []);

    /**
     * Return a paginated results for the current query
     * 
     * @param int $currentPage indicate the current page
     * @param int $recordsPerPage indicate the number of records to display per page
     * 
     * @return PaginatedData|false
     */
    public function paginate($currentPage = null, $recordsPerPage = null);

    /**
     * Return a random result from the current query
     * 
     * @return self|false;
     */
    public function random();

    /**
     * Count total number of elements in a model from results of a query
     * @param string $column
     * 
     * @return int|false
     */
    public function count(string $column = '');
    
    /**
     * Given a column, return the avearage of all values of that
     * column from results of a query
     * @param string $column
     * 
     * @return int|false
     */
    public function avg(string $column);
    
    /**
     * Given a column, return the element in a model with greatest value of that
     * column from results of a query
     * @param string $column
     * 
     * @return int|false
     */
    public function max(string $column);
        
    /**
     * Given a column, return the element in a model with smallest value of that
     * column from results of a query
     * @param string $column
     * 
     * @return int|false
     */
    public function min(string $column);

    /**
     * update a model
     * 
     * @param array $values
     * @param int $id
     * @param bool $is_protected
     * 
     * @return self|bool
     */
    public function update($values, $id=0, $is_protected = true);

    /**
     * update a model
     * @param int $id
     * 
     * @return bool
     */
    public function delete($id = 0);

    /**
     * restore a soft deleted model
     * 
     * @param int $id
     * 
     * @return self|bool
     * @throws Exception
     */
    public function restore($id = 0);

    /**
     * limit the number results from a query
     * 
     * @param int $amount the maximum number of query results to show
     * 
     * @return self
     */
    public function limit($amount);

    /**
     * set the position of the first query result
     * 
     * @param int $position indicates the position of the first query result
     * 
     * @return self
     */
    public function offset($postion);

    /**
     * Add a where clause to the query instance
     * 
     * @param string $column
     * @param string|null $operatorOrValue
     * @param mixed $value
     * 
     * @return self
     */
    public function where($column, $operatorOrValue = null, $value = null);
    
    /**
     * Add a where clause to the query instance
     * 
     * @param string $column
     * @param mixed $value
     * 
     * @return self
     */
    public function whereLike($column, $value = null);

    /**
     * Add a where clause to the query instance
     * 
     * @param string $column
     * @param mixed $value
     * 
     * @return self
     */
    public function whereNotLike($column, $value = null);

    /**
     * Add a where clause to the query instance
     * 
     * @param string $column
     * @param mixed $value
     * 
     * @return self
     */
    public function whereLessThan($column, $value = null);

    /**
     * Add a where clause to the query instance
     * 
     * @param string $column
     * @param mixed $value
     * 
     * @return self
     */
    public function whereGreaterThan($column, $value = null);

    /**
     * Add a where clause to the query instance
     * 
     * @param string $column
     * @param mixed $value
     * 
     * @return self
     */
    public function whereLessThanOrEqual($column, $value = null);

    /**
     * Add a where clause to the query instance
     * 
     * @param string $column
     * @param mixed $value
     * 
     * @return self
     */
    public function whereGreaterThanOrEqual($column, $value = null);

    /**
     * Add a where clause to the query instance
     * 
     * @param string $column
     * @param mixed $value
     * 
     * @return self
     */
    public function whereEqual($column, $value = null);

    /**
     * Add a where clause to the query instance
     * 
     * @param string $column
     * @param mixed $value
     * 
     * @return self
     */
    public function whereNotEqual($column, $value = null);

    /**
     * Add a where clause to the query instance
     * 
     * @param string $column
     * @param string|null $operatorOrValue
     * @param mixed $value
     * 
     * @return self
     */
    public function orWhere($column, $operatorOrValue = null, $value = null);

    /**
     * Add a where clause to the query instance
     * 
     * @param string $column
     * @param mixed $value
     * 
     * @return self
     */
    public function orWhereLike($column, $value = null);

    /**
     * Add a where clause to the query instance
     * 
     * @param string $column
     * @param mixed $value
     * 
     * @return self
     */
    public function orWhereNotLike($column, $value = null);
    
    /**
     * Add a where clause to the query instance
     * 
     * @param string $column
     * @param mixed $value
     * 
     * @return self
     */
    public function orWhereLessThan($column, $value = null);

    /**
     * Add a where clause to the query instance
     * 
     * @param string $column
     * @param mixed $value
     * 
     * @return self
     */
    public function orWhereGreaterThan($column, $value = null);

    /**
     * Add a where clause to the query instance
     * 
     * @param string $column
     * @param mixed $value
     * 
     * @return self
     */
    public function orWhereLessThanOrEqual($column, $value = null);

    /**
     * Add a where clause to the query instance
     * 
     * @param string $column
     * @param mixed $value
     * 
     * @return self
     */
    public function orWhereGreaterThanOrEqual($column, $value = null);

    /**
     * Add a where clause to the query instance
     * 
     * @param string $column
     * @param mixed $value
     * 
     * @return self
     */
    public function orWhereEqual($column, $value = null);

    /**
     * Add a where clause to the query instance
     * 
     * @param string $column
     * @param mixed $value
     * 
     * @return self
     */
    public function orWhereNotEqual($column, $value = null);

    /**
     * Begin a Transaction (all subsequent statements will be executed in that transaction)
     */
    public function beginTransaction();

    /**
     * commit all changes made in the transaction chain
     * 
     * @return void
     */
    public function commit();

    /**
     * rollback all changes made in the transaction chain
     * 
     * @return void
     */
    public function rollback();

    /**
     * rollback all changes made in the transaction chain
     * @param string $column
     * 
     * @return self
     */
    public function distinct(string $column);
}