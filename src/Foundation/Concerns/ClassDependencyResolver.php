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
            $type = $parameter->getType();

            // Only a single class/interface type can be autowired. Anything else —
            // no type, a builtin (string/int/bool/…), or a union/intersection —
            // falls back to the default value, then null (if nullable), else fails.
            // (The old code called App::make() on builtins and on union types'
            // non-existent getName(), which fatally crashed autowiring.)
            if ($type === null || !$type instanceof \ReflectionNamedType || $type->isBuiltin()) {
                if ($parameter->isVariadic()) {
                    continue; // no variadic arguments to inject
                }
                if ($parameter->isDefaultValueAvailable()) {
                    $dependencies[] = $parameter->getDefaultValue();
                } elseif ($type !== null && $type->allowsNull()) {
                    $dependencies[] = null;
                } else {
                    throw new BaseException("Cannot resolve dependency \${$parameter->name}");
                }
                continue;
            }

            // A class/interface type → resolve from the container. If it can't be
            // resolved and the param is nullable/optional, fall back instead of
            // crashing.
            try {
                $dependencies[] = App::make($type->getName());
            } catch (\Throwable $e) {
                if ($type->allowsNull()) {
                    $dependencies[] = null;
                } elseif ($parameter->isDefaultValueAvailable()) {
                    $dependencies[] = $parameter->getDefaultValue();
                } else {
                    throw $e;
                }
            }
        }

        return $dependencies;
    }
}
