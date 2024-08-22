<?php

namespace Eyika\Atom\Framework\Support;

use ArrayAccess;
use Eyika\Atom\Framework\Exceptions\NotImplementedException;

Class Jsonable
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
     * @return string
     */
    public function toJson()
    {
        return json_encode($this->data);
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
