<?php

namespace Eyika\Atom\Framework\Support\Facade;

/**
 * Facade for interacting with the application container.
 *
 * @method static void bind(string $key, callable|mixed $resolver) Bind a resolver, service or value to the container.
 * @method static void singleton(string $key, callable|mixed $resolver) Bind a singleton resolver, service or value to the container.
 * @method static mixed instance(string $accessor, mixed $instance) Store a shared instance in the container.
 * @method static mixed make(string $key) Resolve an instance from the container by its key.
 */
class App extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string The accessor for the container.
     */
    protected static function getFacadeAccessor()
    {
        return 'app';
    }
}
