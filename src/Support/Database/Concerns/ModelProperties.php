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
     * Indicates what database attributes of the model can be exposed outside the application
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
    protected $child;

    /**
     * Name of relationship model to get with current query
     * 
     * @property string
     */
    protected $with_model_name = "";

    /**
     * The placeholder for model dynamic properties
     * 
     * @property array $dynamicProperties;
     */
    protected $dynamicProperties = [];

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
}