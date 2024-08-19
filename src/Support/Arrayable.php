<?php

namespace Eyika\Atom\Framework\Support;

use ArrayAccess;
use Eyika\Atom\Framework\Exceptions\NotImplementedException;

Class Arrayable implements ArrayAccess
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

    public function offsetExists(mixed $offset): bool
    {
        return false;
    }

    public function offsetGet(mixed $offset): mixed
    {
        return false;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        
    }

    public function offsetUnset(mixed $offset): void
    {
        
    }

    /**
     * Add an element to the array using "dot" notation if it doesn't exist.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return self
     */
    public function add($key, $value)
    {
        $this->data = Arr::add($this->data, $key, $value);
        return $this;
    }

    /**
     * Collapse the array of arrays into a single array.
     *
     * @return self
     */
    public function collapse()
    {
        $this->data = Arr::collapse($this->data);
        return $this;
    }

    /**
     * Cross join the given arrays into the instance, so install now contains all possible permutations.
     *
     * @param  iterable  ...$arrays
     * @return self
     */
    public function crossJoin(...$arrays)
    {
        $results = [[]];
        array_push($arrays, $this->data);

        foreach ($arrays as $index => $array) {
            $append = [];

            foreach ($results as $product) {
                foreach ($array as $item) {
                    $product[$index] = $item;

                    $append[] = $product;
                }
            }

            $results = $append;
        }

        $this->data = $results;

        return $this;
    }

    /**
     * Divide the array into two arrays. One with keys and the other with values.
     *
     * @param  array  $array
     * @return array
     */
    public function divide()
    {
        return [array_keys($this->data), array_values($this->data)];
    }

    /**
     * Flatten the multi-dimensional associative array of the instance with dots.
     *
     * @param  string  $prepend
     * @return self
     */
    public function dot($prepend = '')
    {
        $this-> data = Arr::dot($this->data, $prepend);

        return $this;
    }

    /**
     * Get all of the instance's array except for a specified array of keys.
     *
     * @param  array|string  $keys
     * @return array
     */
    public function except($keys)
    {
        $array = $this->data;
        Arr::forget($array, $keys);

        return $array;
    }

    /**
     * Determine if the given key exists in the provided array.
     *
     * @param  string|int  $key
     * @param  bool $use_values
     * @return bool
     */
    public function exists($key, $use_values=false)
    {
        return Arr::exists($key, $use_values);
    }

    /**
     * Return the first element in the array instance passing a given truth test.
     *
     * @param  callable|null  $callback
     * @param  mixed  $default
     * @return mixed
     */
    public function first(callable $callback = null, $default = null)
    {
        return Arr::first($this->data, $callback, $default);
    }

    /**
     * Return the last element in array instance passing a given truth test.
     *
     * @param  callable|null  $callback
     * @param  mixed  $default
     * @return mixed
     */
    public function last(callable $callback = null, $default = null)
    {
        Arr::last($this->data, $callback, $default);
    }

    /**
     * Return the last key in the instance.
     *
     * @return mixed
     */
    public function lastKey()
    {
        return Arr::lastKey($this->data);
    }

    /**
     * Flatten a multi-dimensional array into a single level.
     *
     * @param  int  $depth
     * @return self
     */
    public function flatten($depth = INF)
    {
        Arr::flatten($this->data);
        return $this;
    }

    /**
     * Remove one or many array items from the array instance using "dot" notation.
     *
     * @param  array|string  $keys
     * @return self
     */
    public function forget($keys)
    {
        $array = $this->data;
        Arr::forget($this->data, $keys);
        return $this;
    }

    /**
     * Get an item from an array using "dot" notation.
     *
     * @param  string|int|null  $key
     * @param  mixed  $default
     * @return self
     */
    public function get($key, $default = null)
    {
        return Arr::get($this->data, $default);
    }

    /**
     * Check if an item or items exist in an array using "dot" notation.
     *
     * @param  string|array  $keys
     * @return bool
     */
    public function has($keys)
    {
        return Arr::has($this->data, $keys);
    }

    /**
     * Determine if any of the keys exist in an array using "dot" notation.
     *
     * @param  string|array  $keys
     * @return bool
     */
    public function hasAny($keys)
    {
        return Arr::hasAny($this->data, $keys);
    }

    /**
     * Determines if the array is associative.
     *
     * An array is "associative" if it doesn't have sequential numerical keys beginning with zero.
     *
     * @return bool
     */
    public function isAssoc()
    {
        return Arr::isAssoc($this->data);
    }

    /**
     * Get a subset of the items from the given array.
     *
     * @param  array  $array
     * @param  array|string  $keys
     * @return array
     */
    public function only($array, $keys)
    {
        return array_intersect_key($array, array_flip((array) $keys));
    }

    /**
     * Pluck an array of values from an array.
     *
     * @param  string|array  $value
     * @param  string|array|null  $key
     * @return array
     */
    public function pluck($value, $key = null)
    {
        return Arr::pluck($this->data, $value, $key);
    }

    /**
     * Explode the "value" and "key" arguments passed to "pluck".
     *
     * @param  string|array  $value
     * @param  string|array|null  $key
     * @return array
     */
    // protected static function explodePluckParameters($value, $key)
    // {
    //     $value = is_string($value) ? explode('.', $value) : $value;

    //     $key = is_null($key) || is_array($key) ? $key : explode('.', $key);

    //     return [$value, $key];
    // }

    /**
     * Push an item onto the beginning of an array.
     *
     * @param  mixed  $value
     * @param  mixed  $key
     * @return self
     */
    public function prepend($value, $key = null)
    {
        Arr::prepend($this->data, $value, $key);

        return $this;
    }

    /**
     * Get a value from the array, and remove it.
     *
     * @param  string  $key
     * @param  mixed  $default
     * @return mixed
     */
    public function pull($key, $default = null)
    {
        $value = Arr::get($this->data, $key, $default);

        return $value;
    }

    /**
     * Get one or a specified number of random values from an array.
     *
     * @param  int|null  $number
     * @return mixed
     *
     * @throws \InvalidArgumentException
     */
    public function random($number = null)
    {
        $results = Arr::random($this->data, $number);

        return $results;
    }

    /**
     * Set an array item to a given value using "dot" notation.
     *
     * If no key is given to the method, the entire array will be replaced.
     *
     * @param  array  $array
     * @param  string|null  $key
     * @param  mixed  $value
     * @return self
     */
    public function set(&$array, $key, $value)
    {
        Arr::set($this->data, $key, $value);

        return $this;
    }

    /**
     * Shuffle the given array and return the result.
     *
     * @param  array  $array
     * @param  int|null  $seed
     * @return self
     */
    public function shuffle($array, $seed = null)
    {
        $this->data = Arr::shuffle($this->data, $seed);

        return $this;
    }

    /**
     * Sort the array using the given callback or "dot" notation.
     *
     * @param  callable|string|null  $callback
     * @return self
     */
    public function sort($callback = null)
    {
        $this->data = Arr::sort($this->data, $callback);

        return $this;
    }

    /**
     * Make an arrays collection out of the given array
     *
     * @param  array  $array
     * @return Collection
     */
    // public function collect($array)
    // {
    //     return Collection::makeNew($array);
    // }

    /**
     * Run a map over each of the items.
     *
     * @param  callable  $callback
     * @return self
     */
    public function map(callable $callback)
    {
        $this->data = Arr::map($this->data, $callback);

        return $this;
    }

    /**
     * Run a callback on each items of the array
     * 
     * @param callable $callback
     * @return void
     */
    public function each($callback)
    {
        Arr::each($this->data, $callback);
    }

    /**
     * Recursively sort an array by keys and values.
     *
     * @param  array  $array
     * @return self
     */
    public function sortRecursive($array)
    {
        $this->data = Arr::sortRecursive($this->data, $array);
        return $this;
    }

    /**
     * Convert the array into a query string.
     *
     * @return string
     */
    public function query()
    {
        return Arr::query($this->data);
    }

    /**
     * Filter the array using the given callback.
     *
     * @param  callable  $callback
     * @return array
     */
    public function where($array, callable $callback)
    {
        return Arr::where($this->data, $callback);
    }

    /**
     * If the given value is not an array and not null, wrap it in one.
     *
     * @param  mixed  $value
     * @return array
     */
    public function wrap($value)
    {
        throw new NotImplementedException('method is not yet implemented');
        if (is_null($value)) {
            return [];
        }

        return is_array($value) ? $value : [$value];
    }
}
    