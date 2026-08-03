<?php

namespace Eyika\Atom\Framework\Support\Database\Contracts;

use Eyika\Atom\Framework\Support\Auth\User;
use Eyika\Atom\Framework\Support\Database\Model;
use Eyika\Atom\Framework\Support\Database\PaginatedData;

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
     * The attributes that should be cast to native types.
     *
     * Supported types: 'boolean', 'bool', 'integer', 'int', 'float', 'double', 'string', 'array', 'json', 'object'
     *
     * @var array $casts
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
     * @return Model|User
     */
    public static function getBuilder();
    /**
     * Order query by a culumn in a direction "ASC" or "DESC"
     * @param string $column
     * @param string $direction
     * 
     * @return Model
     */
    public function _orderBy($column = "id", $direction = "ASC");

    // public function addSelect()

    /**
     * Fill a model with array of values
     * @param array $values
     * 
     * @return Model
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
    public function _raw($sql, $bind);
    
    /**
     * create a model from array values and save to db
     * @param array $values
     * @param bool $is_protected 'wether to hide or show protected values'
     * @param array $select 'what parameters of model to fetch in results'
     * 
     * @return Model|bool
     */
    public function _create($values, $is_protected = true, $select = []);

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
     * @return Model|false
     */
    public function _find($id = 0, $is_protected = true);

    /**
     * Find a model by its id, execute the closure if not found
     * @param int $id
     * @param bool $is_protected 'wether to hide or show protected values'
     * @param callable $callable
     * 
     * @return Model|false
     */
    public function _findOr($id = 0, $is_protected = true, $callable = null);

    /**
     * Alias of Find with no id provided
     * Retrieves the first of all results of a query
     * @param bool $is_protected 'wether to hide or show protected values'
     * 
     * @return Model|false
     */
    public function _first($is_protected = true);

    /**
     * Retrieves the first of all results of a query
     * No previous or subsequent where clause is required
     * @param string $column
     * @param string|null $operatorOrValue
     * @param mixed $value
     * @param bool $is_protected 'wether to hide or show protected values'
     * 
     * @return Model|false
     */
    public function _firstWhere($column, $operatorOrValue = null, $value = null, $is_protected = true);

    /**
     * Retrieve model by key value or create it if it doesn't exist from array values
     * 
     * @param array $search will be used to filter wether to create or not and
     * @param array $keyvalues the values to be insterted if search returns empty
     * @param bool $is_protected 'wether to hide or show protected values'
     * @param array $select 'what parameters of model to fetch in results'
     * 
     * @return Model|bool
     */
    public function _firstOrCreate($search, $keyvalues, $is_protected = true, $select = []);

    /**
     * First matching row, or the result of $callable when nothing matches.
     *
     * @param callable|null $callable
     * @param bool          $is_protected
     * @return self|mixed
     */
    public function _firstOr($callable = null, $is_protected = true);

    /**
     * Retrieve model its current values or instantiate it if it doesn't exist from array values
     * The model still needs to be save to the DB by calling save()
     * 
     * @return Model|bool
     */
    public function _firstOrNew($search, $values = [], $is_protected = true);

    /**
     * Find a model by key and value
     * 
     * @param string $key
     * @param string $value
     * @param bool $is_protected 'wether to hide or show protected values'
     * @param array $select 'what parameters of model to fetch in results'
     * 
     * @return Model|false
     */
    public function _findBy($key, $value, $is_protected = true, $select = []);
    
    /**
     * Find a model by a set of keys and values
     * 
     * @param array $keys
     * @param array $values
     * @param string $or_and 'wether to use OR or AND to join where clauses'
     * @param bool $is_protected 'wether to hide or show protected values'
     * @param array $select 'what parameters of model to fetch in results'
     * 
     * @return Model|false
     */
    public function _findByArray($keys, $values, $or_and = "AND", $is_protected = true, $select = []);

    /**
     * Find all elements of a model
     *
     * @param bool $is_protected 'wether to hide or show protected values'
     * @param array $select 'what parameters of model to fetch in results'
     *
     * @return \Eyika\Atom\Framework\Support\Collections\Collection
     */
    public function _all($is_protected = true, $select = []);

    /**
     * Attach the related model to a model's query result
     * 
     * @param array<string>|string $models
     * 
     * @return Model|User
     */

    public function _with($models);

    
    /**
     * Alias for all(), Find all elements of a model
     *
     * @param bool $is_protected 'wether to hide or show protected values'
     * @param array $select 'what parameters of model to fetch in results'
     *
     * @return \Eyika\Atom\Framework\Support\Collections\Collection
     */
    public function _get($is_protected = true, $select = []);

    /**
     * Stream results as a memory-efficient LazyCollection (one model per DB-cursor row).
     *
     * @param bool $is_protected
     * @param array $select
     * @return \Eyika\Atom\Framework\Support\Collections\LazyCollection
     */
    public function _cursor($is_protected = true, $select = []);

    /**
     * Alias for cursor().
     *
     * @param bool $is_protected
     * @param array $select
     * @return \Eyika\Atom\Framework\Support\Collections\LazyCollection
     */
    public function _lazy($is_protected = true, $select = []);

    /**
     * Return a paginated results for the current query
     * 
     * @param int $currentPage indicate the current page
     * @param int $recordsPerPage indicate the number of records to display per page
     * @param bool $isProtected 'wether to hide or show protected values'
     * @param array $select 'what parameters of model to fetch in results'
     * @param ?string $routeName used to generate the base url for previous and nextPages
     * 
     * @return PaginatedData|false
     */
    public function _paginate($currentPage = null, $recordsPerPage = null, $isProtected = true, $select = [], $routeName = null);

    /**
     * Return a random result from the current query
     * 
     * @return Model|false;
     */
    public function _random();

    /**
     * Count total number of elements in a model from results of a query
     * @param string $column
     * 
     * @return int
     */
    public function _count(string $column = '');
    
    /**
     * Given a column, return the avearage of all values of that
     * column from results of a query
     * @param string $column
     * 
     * @return int
     */
    public function _avg(string $column);
    
    /**
     * Given a column, return the element in a model with greatest value of that
     * column from results of a query
     * @param string $column
     * 
     * @return int
     */
    public function _max(string $column);
        
    /**
     * Given a column, return the element in a model with smallest value of that
     * column from results of a query
     * @param string $column
     * 
     * @return int
     */
    public function _min(string $column);

    /**
     * Given a column, return the mathematical sum of all values of that
     * column from results of a query
     * @param string $column
     * 
     * @return int
     */
    public function _sum(string $column);

    /**
     * Given a column, return the string result of concatinating all values of that
     * column from results of a query
     * @param string $column
     * 
     * @return string
     */
    public function _group_concat(string $column);
    
    /**
     * Given a column, return the statistical variance population evaluation of all values of that
     * column from results of a query
     * @param string $column
     * 
     * @return int
     */
    public function _var_pop(string $column);

    /**
     * Given a column, return the standard deviation evaluation of all values of that
     * column from results of a query
     * @param string $column
     * 
     * @return int
     */
    public function _stddev(string $column);
    
    /**
     * Given a column, return the bit_and evaluation of all values of that
     * column from results of a query
     * @param string $column
     * 
     * @return int
     */
    public function _bit_and(string $column);

    /**
     * Given a column, return the bit_or evaluation of all values of that
     * column from results of a query
     * @param string $column
     * 
     * @return int
     */
    public function _bit_or(string $column);

    /**
     * Given a column, return the bit_xor evaluation of all values of that
     * column from results of a query
     * @param string $column
     * 
     * @return int
     */
    public function _bit_xor(string $column);

    /**
     * Increment the given column by 1 or the given number of steps
     * 
     * @param string $column
     * @param int $step
     * 
     * @return bool
     */
    public function _increment(string $column, int $step = 1);

    /**
     * Decrement the given column by 1 or the given number of steps
     * 
     * @param string $column
     * @param int $step
     * 
     * @return bool
     */
    public function _decrement(string $column, int $step = 1);

    /**
     * update a model
     * 
     * @param array $values
     * @param int $id
     * @param bool $is_protected
     * 
     * @return Model|bool
     */
    public function _update($values, $id=0, $is_protected = true);

    /**
     * update a model
     * 
     * @param array $values
     * @param int $id
     * @param bool $is_protected
     * 
     * @return Model|bool
     */
    public function _updateOrCreate($values, $id=0, $is_protected = true);

    /**
     * update a model
     * @param int $id
     * 
     * @return bool
     */
    public function _delete($id = 0);

    /**
     * restore a soft deleted model
     * 
     * @param int $id
     * 
     * @return Model|bool
     * @throws Exception
     */
    public function _restore($id = 0);

    /**
     * limit the number results from a query
     * 
     * @param int $amount the maximum number of query results to show
     * 
     * @return Model
     */
    public function _limit($amount);

    /**
     * set the position of the first query result
     * 
     * @param int $position indicates the position of the first query result
     * 
     * @return Model
     */
    public function _offset($postion);

    /**
     * Add a where clause to the query instance
     * 
     * @param string $column
     * @param string|null $operatorOrValue
     * @param mixed $value
     * 
     * @return Model
     */
    public function _where($column, $operatorOrValue = null, $value = null);
    
    /**
     * Add a where clause to the query instance
     * 
     * @param string $column
     * @param array $values
     * 
     * @return Model
     */
    public function _whereIn($column, array $values);
    
    /**
     * Add a where clause to the query instance
     * 
     * @param string $column
     * @param array $values
     * 
     * @return Model
     */
    public function _whereNotIn($column, array $values);
    
    /**
     * Add a where clause to the query instance
     * 
     * @param string $column
     * @param mixed $value
     * 
     * @return Model
     */
    public function _whereLike($column, $value);

    /**
     * Add a where clause to the query instance
     * 
     * @param string $column
     * @param mixed $value
     * 
     * @return Model
     */
    public function _whereNotLike($column, $value);

    /**
     * Add a where BETWEEN clause for a [min, max] range on a single column.
     *
     * @param string $column
     * @param array $range [min, max]
     *
     * @return Model
     */
    public function _whereBetween($column, array $range);

    /**
     * Add a where NOT BETWEEN clause for a [min, max] range on a single column.
     *
     * @param string $column
     * @param array $range [min, max]
     *
     * @return Model
     */
    public function _whereNotBetween($column, array $range);

    /**
     * Add a where clause to the query instance
     *
     * @param string $column
     * @param mixed $value
     *
     * @return Model
     */
    public function _whereLessThan($column, $value);

    /**
     * Add a where clause to the query instance
     * 
     * @param string $column
     * @param mixed $value
     * 
     * @return Model
     */
    public function _whereGreaterThan($column, $value);

    /**
     * Add a where clause to the query instance
     * 
     * @param string $column
     * @param mixed $value
     * 
     * @return Model
     */
    public function _whereLessThanOrEqual($column, $value);

    /**
     * Add a where clause to the query instance
     * 
     * @param string $column
     * @param mixed $value
     * 
     * @return Model
     */
    public function _whereGreaterThanOrEqual($column, $value);

    /**
     * Add a where clause to the query instance
     * 
     * @param string $column
     * 
     * @return Model
     */
    public function _whereNull($column);

    /**
     * Add a where clause to the query instance
     * 
     * @param string $column
     * 
     * @return Model
     */
    public function _whereNotNull($column);

    /**
     * Add a where clause to the query instance
     * 
     * @param string $column
     * @param mixed $value
     * 
     * @return Model
     */
    public function _whereEqual($column, $value);

    /**
     * Add a where clause to the query instance
     * 
     * @param string $column
     * @param mixed $value
     * 
     * @return Model
     */
    public function _whereNotEqual($column, $value);

    /**
     * Add a where clause to the query instance
     * 
     * @param string $column
     * @param string|null $operatorOrValue
     * @param mixed $value
     * 
     * @return Model
     */
    public function _orWhere($column, $operatorOrValue = null, $value = null);

    /**
     * Add a where clause to the query instance
     * 
     * @param string $column
     * @param mixed $value
     * 
     * @return Model
     */
    public function _orWhereLike($column, $value);

    /**
     * Add a where clause to the query instance
     * 
     * @param string $column
     * @param mixed $value
     * 
     * @return Model
     */
    public function _orWhereNotLike($column, $value);
    
    /**
     * Add a where clause to the query instance
     * 
     * @param string $column
     * @param mixed $value
     * 
     * @return Model
     */
    public function _orWhereLessThan($column, $value);

    /**
     * Add a where clause to the query instance
     * 
     * @param string $column
     * @param mixed $value
     * 
     * @return Model
     */
    public function _orWhereGreaterThan($column, $value);

    /**
     * Add a where clause to the query instance
     * 
     * @param string $column
     * @param mixed $value
     * 
     * @return Model
     */
    public function _orWhereLessThanOrEqual($column, $value);

    /**
     * OR variant of whereIn().
     *
     * @param string $column
     * @param array  $values
     * @return self
     */
    public function _orWhereIn($column, $values);

    /**
     * OR variant of whereNotIn().
     *
     * @param string $column
     * @param array  $values
     * @return self
     */
    public function _orWhereNotIn($column, $values);

    /**
     * Add a where clause to the query instance
     * 
     * @param string $column
     * @param mixed $value
     * 
     * @return Model
     */
    public function _orWhereGreaterThanOrEqual($column, $value);

    /**
     * Add a where clause to the query instance
     * 
     * @param string $column
     * @param mixed $value
     * 
     * @return Model
     */
    public function _orWhereEqual($column, $value);

    /**
     * Add a where clause to the query instance
     * 
     * @param string $column
     * @param mixed $value
     * 
     * @return Model
     */
    public function _orWhereNotEqual($column, $value);

    /**
     * Add an orWhereNull clause to the query instance
     * 
     * @param string $column
     * 
     * @return Model
     */
    public function _orWhereNull($column);

    /**
     * Add an orWhereNotNull clause to the query instance
     * 
     * @param string $column
     * 
     * @return Model
     */
    public function _orWhereNotNull($column);

    /**
     * Begin a Transaction (all subsequent statements will be executed in that transaction)
     */
    public function _beginTransaction();

    /**
     * commit all changes made in the transaction chain
     * 
     * @return void
     */
    public function _commit();

    /**
     * rollback all changes made in the transaction chain
     * 
     * @return void
     */
    public function _rollback();

    /**
     * Add a join clause to the query instance
     * 
     * @param string $table
     * @param string $first
     * @param string $operator
     * @param string $second
     * 
     * @return Model
     */
    public function _join($table, $first, $operator, $second);

    /**
     * Add a join clause to the query instance
     * 
     * @param string $table
     * @param string $first
     * @param string $operator
     * @param string $second
     * 
     * @return Model
     */
    public function _leftJoin($table, $first, $operator, $second);

    /**
     * Add a join clause to the query instance
     * 
     * @param string $table
     * @param string $first
     * @param string $operator
     * @param string $second
     * 
     * @return Model
     */
    public function _rightJoin($table, $first, $operator, $second);

    /**
     * Add a join clause to the query instance
     * 
     * @param string $table
     * @param string $first
     * @param string $operator
     * @param string $second
     * 
     * @return Model
     */
    public function _fullOuterJoin($table, $first, $operator, $second);

    /**
     * specify that the query should return distinct results based on specified column
     * @param string $column
     * 
     * @return Model
     */
    public function _distinct(string $column);

    /**
     * Add FOR UPDATE to the next read, locking the matched rows for the transaction.
     *
     * @return self
     */
    public function _lockForUpdate();
}
