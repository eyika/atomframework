<?php

namespace Eyika\Atom\Framework\Support\Database\Concerns;

use Eyika\Atom\Framework\Support\Database\Model;
use Eyika\Atom\Framework\Support\Database\Contracts\UserModelInterface;

trait ModelProperties
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    public $table;

    /**
     * The primary key for the model in db
     *
     * @var string
     */
    public $primaryKey = 'id';

    /**
     * Column that route-model binding resolves a URL segment against.
     *
     * Defaults to the primary key, so `/users/{user}` binds by id. Override to bind by a
     * human-readable column instead — `/posts/{post}` matching a slug:
     *
     *     public function getRouteKeyName(): string { return 'slug'; }
     */
    public function getRouteKeyName(): string
    {
        return $this->primaryKey;
    }

    /**
     * Wether the model can be soft deleted
     * 
     * @var string
     */
    public $softdeletes = true;

    /**
     * id property of the model
     * 
     * @var int
     */
    public $id = 0;

    /**
     * The "type" of the primary key ID.
     *
     * @var string
     */
    protected $keyType = 'int';

    /**
     * Indicates what database attributes of the model can be filled at once
     * 
     * @var array
     */
    protected const fillable = [
        'id', 'created_at', 'updated_at', 'deleted_at'
    ];

    /**
     * Indicates what database attributes of the model can be exposed outside the application.
     *
     * This is an OUTPUT filter, applied by toArray()/serialization — it is deliberately NOT
     * applied to the SELECT column list. Excluding guarded columns from the read used to leave
     * the model's own property null, so application logic could not see its own data (a service
     * reading `created_at` off a `get()` result silently got null), while adding no protection:
     * `toArray()` guards by default and is what the JSON response path calls.
     *
     * @var array
     */
    protected const guarded = ['deleted_at'];

    /**
     * Indicates what database attributes of the model can be exposed outside the application
     * 
     * @var array
     */
    protected const defaultGuarded = ['incrementing', 'exists', 'wasRecentlyCreated'];

    /**
     * The attributes that should be cast to native types.
     *
     * Supported types: 'boolean', 'bool', 'integer', 'int', 'float', 'double', 'string', 'array', 'json', 'object'
     *
     * @var array
     */
    protected const casts = [];

    /**
     * Indicates values that should be encrypted when saving and decrypted when retrieving
     *
     * @var array
     */
    protected const encrypted = [];

    /**
     * Indicates values that should be given a duplicate hashed column
     * 
     * @var array
     */
    protected const ignore_hash_replica = [];

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    protected $incrementing = true;

    /**
     * The number of models to return for pagination.
     *
     * @var int
     */
    protected $recordsPerPage = 15;

    /**
     * Indicates if the model exists.
     *
     * @var bool
     */
    protected $exists = false;

    /**
     * Indicates if the model was inserted during the current request lifecycle.
     *
     * @var bool
     */
    protected $wasRecentlyCreated = false;

    /**
     * The operators for query
     * 
     * @var array|string
     */
    protected $operators;

    /**
     * The booleans to add queries together
     * 
     * @var array|string
     */
    protected $or_ands;

    /**
     * The join statements to add to the query
     * 
     * @var array
     */
    protected $joins = [];

    /**
     * The filter key values
     * 
     * @var array|null
     */
    protected $bind_or_filter;

    /**
     * Wether queries are currently running in a transaction
     * 
     * @var bool
     */
    protected $transaction_mode;

    /**
     * Sets how the query should be ordered
     * 
     * @var string
     */
    protected $order = "";

    /**
     * The child class that currently using the parent class
     * 
     * @var Model|Model&UserModelInterface
     */
    // protected $child;

    /**
     * Name of relationship model to get with current query
     * 
     * @property string[]
     */
    protected $with_model_names = [];

    /**
     * The placeholder for model dynamic properties
     * 
     * @property array $dynamicProperties;
     */
    protected $dynamicProperties = [];
    /**
     * The placeholder for model dynamic properties
     * 
     * @property array $relationshipItems;
     */
    protected $relationshipItems = [];

    /**
     * The name of the "created at" column.
     *
     * @var string|null
     */
    const CREATED_AT = 'created_at';

    /**
     * The name of the "updated at" column.
     *
     * @var string|null
     */
    const UPDATED_AT = 'updated_at';

    /**
     * The name of the "suffix" for the hashed replica for encrypted columns.
     * 
     * @var array
     */
    protected const hashed_col_suffix = '_hash';
}
