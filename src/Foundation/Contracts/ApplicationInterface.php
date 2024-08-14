<?php

namespace Eyika\Atom\Framework\Foundation\Contracts;

interface ApplicationInterface
{
    // Bind a service to the container
    public function bind(string $key, $resolver): void;

    // Bind a singleton service to the container
    public function singleton(string $key, $resolver): mixed;

    // Resolve a service and its dependencies
    public function make($key): mixed;

    // Automatically resolve class dependencies
    public function resolve($class): mixed;

    // Resolve the dependencies of a class constructor
    public function resolveDependencies(array $parameters): array;

    // Swap or set an instance
    public function instance(string $accessor, mixed $instance);
}
