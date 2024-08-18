<?php

namespace Eyika\Atom\Framework\Support\Facade;

use Eyika\Atom\Framework\Exceptions\BaseException;
use Eyika\Atom\Framework\Foundation\Application;

class Facade
{
    /**
     * The application instance being facaded.
     *
     * @var \Eyika\Atom\Framework\Foundation\Application|null
     */
    protected static $app;

    /**
     * The resolved object instances.
     *
     * @var array
     */
    protected static $resolvedInstance;

    /**
     * Indicates if the resolved instance should be cached.
     *
     * @var bool
     */
    protected static $cached = true;

    /**
     * Get the application instance behind the facade.
     * 
     */
    public static function getFacadeApplication(): Application
    {
        return static::$app;
    }

    /**
     * Set the application instance.
     *
     */
    public static function setFacadeApplication(Application $app)
    {
        static::$app = $app;
    }

    protected static function getFacadeAccessor()
    {
        throw new BaseException('Facade does not implement getFacadeAccessor method.');
    }

    /**
     * Hotswap the underlying instance behind the facade.
     *
     * @param  mixed $instance
     * @return void
     */
    public static function swap($instance)
    {
        if (static::$app) {
            static::$app->instance(static::getFacadeAccessor(), $instance);
        }

        static::$resolvedInstance[static::getFacadeAccessor()] = $instance;
    }

    /**
     * Resolve the facade root instance from the container.
     *
     * @param  string  $name
     * @return mixed
     */
    protected static function resolveFacadeInstance($name)
    {
        if (isset(static::$resolvedInstance[$name])) {
            return static::$resolvedInstance[$name];
        }

        if (static::$app) {
            if (static::$cached) {
                return static::$resolvedInstance[$name] = static::$app[$name];
            }

            return static::$app[$name];
        }
    }

    /**
     * Clear a resolved facade instance.
     *
     * @param  string  $name
     * @return void 
     */
    public static function clearResolvedInstance($name)
    {
        unset(static::$resolvedInstance[$name]);
    }

    /**
     * Clear all of the resolved instances.
     *
     * @return void
     */
    public static function clearResolvedInstances()
    {
        static::$resolvedInstance = [];
    }

    /**
     * Get the root object behind the facade.
     *
     * @return mixed
     */
    public static function getFacadeRoot()
    {
        return static::resolveFacadeInstance(static::getFacadeAccessor());
    }

    public static function __callStatic($method, $arguments)
    {
        $instance = static::$app->make(static::getFacadeAccessor());

        if (!method_exists($instance, $method)) {
            throw new BaseException("Method {$method} does not exist on the underlying service.");
        }

        return $instance->$method(...$arguments);
    }
}
