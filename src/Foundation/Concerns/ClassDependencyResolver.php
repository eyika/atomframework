<?php

namespace Eyika\Atom\Framework\Foundation\Concerns;

use Eyika\Atom\Framework\Exceptions\BaseException;
use Eyika\Atom\Framework\Support\Facade\App;
use ReflectionClass;
use ReflectionParameter;

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

    /**
     * Resolve the dependencies of a class constructor
     * 
     * @property ReflectionParameter[] $parameters
     */
    protected function resolveDependencies($parameters): array
    {
        $dependencies = [];

        foreach ($parameters as $parameter) {
            /** @var ReflectionParameter $parameter */
            $dependency = $parameter->getType();

            if ($dependency === null) {
                if ($parameter->isDefaultValueAvailable()) {
                    $dependencies[] = $parameter->getDefaultValue();
                } else {
                    throw new BaseException("Cannot resolve dependency {$parameter->name}");
                }
            } else {
                $dependencies[] = App::make($dependency->getName());
            }
        }

        return $dependencies;
    }
}