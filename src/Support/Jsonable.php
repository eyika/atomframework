<?php

namespace Eyika\Atom\Framework\Support;

use ArrayAccess;
use Eyika\Atom\Framework\Exceptions\NotImplementedException;

use Eyika\Atom\Framework\Support\Contracts\Jsonable as JsonableContract;

/**
 * Declares the contract it already satisfied in shape but not in type. Collection dispatch tests
 * `instanceof`, so a class that merely *has* toJson() is invisible to it.
 */
Class Jsonable implements JsonableContract
{
    
    /**
     * The underlying array data.
     *
     * @var array
     */
    protected $data;

    /**
     * Create a new instance of the class.
     *
     * @param  string  $value
     * @return void
     */
    public function __construct($data = [])
    {
        $this->data = $data;
    }

    /**
     * Convert the data to a json string
     *
     * @param  int  $options
     * @return string
     */
    public function toJson($options = 0)
    {
        return json_encode($this->data, $options);
    }

    /**
     * Convert the data to an object
     * 
     * @return object
     */
    public function toObject()
    {
        return (object)$this->data;
    }
}
