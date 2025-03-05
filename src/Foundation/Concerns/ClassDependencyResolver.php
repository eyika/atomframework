<?php

namespace Eyika\Atom\Framework\Foundation\Concerns;

use Eyika\Atom\Framework\Exceptions\BaseException;
use ReflectionClass;

trait ClassDependencyResolver
{
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