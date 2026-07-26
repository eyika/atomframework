<?php

namespace Eyika\Atom\Framework\Support\Database;

use Exception;
use Eyika\Atom\Framework\Support\Arr;
use Eyika\Atom\Framework\Support\Auth\User;
use Eyika\Atom\Framework\Support\Concerns\DeepClonesSelf;
use Eyika\Atom\Framework\Support\Database\Concerns\HasRelationships;
use Eyika\Atom\Framework\Support\Database\Concerns\InitsModelEvents;
use Eyika\Atom\Framework\Support\Database\Concerns\QueryBuilder;
use Eyika\Atom\Framework\Support\Database\Contracts\ModelInterface;

/**
 * @method static self|bool create(array $values, bool $is_protected = true, array $select = [])
 * @method static self|null find(int $id = 0, bool $is_protected = true)
 * @method static User|null findByUsername($name, $is_protected = true)
 * @method static User|null findByEmail(string $email, $is_protected = true)
 * @method static self|null first($is_protected = true)
 * @method static self|null findOr(int $id = 0, bool $is_protected = true, callable $callable = null)
 * @method static self|null firstWhere(string $column, string|null $operatorOrValue = null, mixed $value = null, bool $is_protected = true)
 * @method static self|null firstOrCreate(array $search, array $keyvalues, bool $is_protected = true, array $select = [])
 * @method static self|null findBy(string $key, string $value, bool $is_protected = true, array $select = [])
 * @method static self|null findByArray(array $keys, array $values, string $or_and = "AND", bool $is_protected = true, array $select = [])
 * @method static array|false all(bool $is_protected = true, array $select = [])
 * @method static array|false get(bool $is_protected = true, array $select = [])
 * @method self lockForUpdate()
 * @method static PaginatedData|false paginate(int $currentPage = null, int $recordsPerPage = null, bool $isProtected = true, array $select = [], ?string $routeName = null)
 * @method static self|null random()
 * @method static int count(string $column = '')
 * @method static int avg(string $column)
 * @method static int max(string $column)
 * @method static int min(string $column)
 * @method static int sum(string $column)
 * @method static int var_pop(string $column)
 * @method static int stddev(string $column)
 * @method static int bit_and(string $column)
 * @method static int bit_xor(string $column)
 * @method static bool increment(string $column, int $step = 1)
 * @method static bool decrement(string $column, int $step = 1)
 * @method static string group_concat(string $column)
 * @method static self|bool update($values, $id=0, $is_protected = true)
 * @method static self|bool updateOrCreate($values, $id=0, $is_protected = true)
 * @method static bool delete($id = 0)
 * @method static self|bool restore($id = 0)
 * @method static self limit($amount)
 * @method static self offset($postion)
 * @method static self where($column, $operatorOrValue = null, $value = null)
 * @method static self whereIn($column, array $values)
 * @method static self whereNotIn($column, array $values)
 * @method static self whereLike($column, $value)
 * @method static self whereNotLike($column, $value)
 * @method static self whereBetween($column, array $range)
 * @method static self whereNotBetween($column, array $range)
 * @method static self whereLessThan($column, $value)
 * @method static self whereGreaterThan($column, $value)
 * @method static self whereLessThanOrEqual($column, $value)
 * @method static self whereGreaterThanOrEqual($column, $value)
 * @method static self whereNull($column)
 * @method static self whereNotNull($column)
 * @method static self whereEqual($column, $value)
 * @method static self whereNotEqual($column, $value)
 * @method static self orWhere($column, $operatorOrValue = null, $value = null)
 * @method static self orWhereLike($column, $value)
 * @method static self orWhereNotLike($column, $value)
 * @method static self orWhereLessThan($column, $value)
 * @method static self orWhereGreaterThan($column, $value)
 * @method static self orWhereGreaterThanOrEqual($column, $value)
 * @method static self orWhereEqual($column, $value)
 * @method static self orWhereNotEqual($column, $value)
 * @method static self orWhereNull($column)
 * @method static self orWhereNotNull($column)
 * @method static void beginTransaction()
 * @method static void commit()
 * @method static void rollback()
 * @method static self distinct(string $column)
 * @method static self join($table, $first, $operator, $second)
 * @method static self leftJoin($table, $first, $operator, $second)
 * @method static self rightJoin($table, $first, $operator, $second)
 * @method static self fullOuterJoin($table, $first, $operator, $second)
 */

abstract class Model implements ModelInterface
{
    use QueryBuilder, InitsModelEvents, DeepClonesSelf, HasRelationships;

    protected const DYNAMIC_STATIC_METHODS = [
        'create', 'find', 'findByEmail', 'findByUsername', 'findOr', 'first', 'firstOr', 'firstWhere', 'firstOrCreate', 'findBy',
        'findByArray', 'all', 'get', 'paginate', 'random', 'count', 'avg', 'max', 'min', 'increment', 'decrement',
        'sum', 'var_pop', 'stddev', 'bit_and', 'bit_or', 'bit_xor', 'group_concat', 'update',
        'updateOrCreate', 'delete', 'restore', 'limit', 'offset', 'where', 'whereIn',
        'whereNotIn', 'whereNotIn', 'whereLike', 'whereNotLike', 'whereBetween', 'whereNotBetween', 'whereLessThan',
        'whereLessThanOrEqual', 'whereGreaterThanOrEqual', 'whereNull', 'whereNotNull',
        'whereEqual', 'whereNotEqual', 'orWhere', 'orWhereLike', 'orWhereNotLike',
        'orWhereLessThan', 'orWhereGreaterThan', 'orWhereGreaterThan', 'orWhereGreaterThanOrEqual',
        'orWhereEqual', 'orWhereNotEqual', 'orWhereNull', 'orWhereNotNull', 'beginTransaction',
        'commit', 'rollback', 'distinct', 'join', 'leftJoin', 'rightJoin', 'fullOuterJoin', 'lockForUpdate'
    ];

    /**
     * Create a new model instance.
     *
     * @param array  $attributes
     * @return void
     */
    public function __construct(array $values = [])
    {
        $this->prepareModel($values);
    }

    public function __call($name, $arguments)
    {
        // Map instance calls from where() to _where()
        $realMethod = method_exists($this, "_{$name}") ? "_{$name}" : $name;

        if (!method_exists($this, $realMethod)) {
            throw new Exception("Method '{$name}' does not exist on instance.");
        }

        return $this->$realMethod(...$arguments);
    }

    public static function __callStatic($name, $arguments)
    {
        if (!in_array($name, self::DYNAMIC_STATIC_METHODS, true)) {
            throw new Exception("Method '{$name}' does not exist or is not supported by dynamic static calls.");
        }

        // Create a new instance of the model
        $instance = new static();
    
        // Map static calls to the renamed method
        $realMethod = method_exists($instance, "_{$name}") ? "_{$name}" : $name;

        // Ensure the method exists in the QueryBuilder trait
        if (method_exists($instance, $realMethod)) {
            return $instance->$realMethod(...$arguments);
        }
    
        throw new Exception("Method '{$name}' does not exist.");
    }    
}
