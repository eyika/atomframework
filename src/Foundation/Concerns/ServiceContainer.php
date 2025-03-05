<?php

namespace Eyika\Atom\Framework\Foundation\Concerns;

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
}
