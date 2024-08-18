<?php

namespace Eyika\Atom\Framework\Foundation\Concerns;

use Eyika\Atom\Framework\Exceptions\BaseException;
use ReflectionClass;

trait ServiceContainer
{
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

    // Automatically resolve class dependencies
    protected function resolve(string $class): mixed
    {
        $reflectionClass = new ReflectionClass($class);

        if (!$reflectionClass->isInstantiable()) {
            throw new BaseException("Class {$class} is not instantiable.");
        }

        $constructor = $reflectionClass->getConstructor();

        if (is_null($constructor)) {
            return new $class;
        }

        $parameters = $constructor->getParameters();
        $dependencies = $this->resolveDependencies($parameters);

        return $reflectionClass->newInstanceArgs($dependencies);
    }

    // Resolve the dependencies of a class constructor
    protected function resolveDependencies(array $parameters): array
    {
        $dependencies = [];

        foreach ($parameters as $parameter) {
            $dependency = $parameter->getClass();

            if ($dependency === null) {
                if ($parameter->isDefaultValueAvailable()) {
                    $dependencies[] = $parameter->getDefaultValue();
                } else {
                    throw new BaseException("Cannot resolve dependency {$parameter->name}");
                }
            } else {
                $dependencies[] = $this->make($dependency->name);
            }
        }

        return $dependencies;
    }
}
