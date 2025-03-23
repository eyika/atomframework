<?php

namespace Eyika\Atom\Framework\Foundation\Concerns;

use Closure;
use Eyika\Atom\Framework\Exceptions\BaseException;
use ReflectionClass;

trait ServiceContainer
{
    use ClassDependencyResolver;

    protected $bindings = [];
    protected $instances = [];

    // Bind a service to the container
    public function bind(string $key, $resolver): void
    {
        $this->bindings[$key] = $resolver;
    }

    /**
     * Determine if the given abstract type has been bound.
     *
     * @param  string  $abstract
     * @return bool
     */
    public function bound($abstract)
    {
        return isset($this->bindings[$abstract]) ||
               isset($this->instances[$abstract]) ||
               $this->isAlias($abstract);
    }

    /**
     * {@inheritdoc}
     *
     * @return bool
     */
    public function has(string $id): bool
    {
        return $this->bound($id);
    }

    /**
     * Determine if a given string is an alias.
     *
     * @param  string  $name
     * @return bool
     */
    public function isAlias($name)
    {
        return isset($this->aliases[$name]);
    }

    // Bind a singleton service to the container
    public function singleton(string $key, $resolver): void
    {
        $this->bindings[$key] = function() use ($resolver) {
            static $instance;

            if ($instance === null) {
                $instance = is_callable($resolver) ? $resolver() : new $resolver;
            }

            return $instance;
        };
    }

    public function instance(string $accessor, mixed $instance): mixed
    {
        $this->instances[$accessor] = $instance;

        return $instance;
    }

    // Resolve a service and its dependencies
    public function make(string $key): mixed
    {
        if (isset($this->instances[$key])) {
            return $this->instances[$key];
        }

        if (isset($this->bindings[$key])) {
            $resolver = $this->bindings[$key];
            $object = $resolver($this);
        } else {
            $object = $this->resolve($key);
        }

        $this->instances[$key] = $object;
        return $object;
    }

    /**
     * Determine if a given offset exists.
     *
     * @param  string  $key
     * @return bool
     */
    public function offsetExists($key): bool
    {
        return $this->bound($key);
    }

    /**
     * Get the value at a given offset.
     *
     * @param  string  $key
     * @return mixed
     */
    public function offsetGet($key): mixed
    {
        return $this->make($key);
    }

    /**
     * Set the value at a given offset.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return void
     */
    public function offsetSet($key, $value): void
    {
        $this->bind($key, $value instanceof Closure ? $value : fn () => $value);
    }

    /**
     * Unset the value at a given offset.
     *
     * @param  string  $key
     * @return void
     */
    public function offsetUnset($key): void
    {
        unset($this->bindings[$key], $this->instances[$key], $this->resolved[$key]);
    }

    /**
     * Dynamically access container services.
     *
     * @param  string  $key
     * @return mixed
     */
    public function __get($key)
    {
        return $this[$key];
    }

    /**
     * Dynamically set container services.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return void
     */
    public function __set($key, $value)
    {
        $this[$key] = $value;
    }
}
