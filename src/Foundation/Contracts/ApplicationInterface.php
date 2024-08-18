<?php

namespace Eyika\Atom\Framework\Foundation\Contracts;

interface ApplicationInterface
{
    // Bind a service to the container
    public function bind(string $key, $resolver): void;

    // Bind a singleton service to the container
    public function singleton(string $key, $resolver): void;

    // Resolve a service and its dependencies
    public function make(string $key): mixed;

    // Swap or set an instance
    public function instance(string $accessor, mixed $instance): mixed;
}
