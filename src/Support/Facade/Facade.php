<?php

namespace Eyika\Atom\Framework\Support\Facade;

use Closure;
use Eyika\Atom\Framework\Exceptions\BaseException;
use Eyika\Atom\Framework\Exceptions\Console\RuntimeException;
use Eyika\Atom\Framework\Exceptions\NotImplementedException;
use Eyika\Atom\Framework\Foundation\Application;
use Eyika\Atom\Framework\Support\Arrayable;
use Eyika\Atom\Framework\Support\Collections\Collection;

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
     * Indicates if the base class should be called statically if a service method could not be resolved.
     *
     * @var bool
     */
    protected static $callStaticIfServiceMethodNotFound = false;

    /**
     * Run a Closure when the facade has been resolved.
     *
     * @param  \Closure  $callback
     * @return void
     */
    public static function resolved(Closure $callback)
    {
        $accessor = static::getFacadeAccessor();

        if (static::$app->resolved($accessor) === true) {
            $callback(static::getFacadeRoot(), static::$app);
        }

        static::$app->afterResolving($accessor, function ($service, $app) use ($callback) {
            $callback($service, $app);
        });
    }

    /**
     * Convert the facade into a Mockery spy.
     *
     * @return \Mockery\MockInterface
     */
    public static function spy()
    {
        if (! static::isMock()) {
            $class = static::getMockableClass();

            return tap($class ? Mockery::spy($class) : Mockery::spy(), function ($spy) {
                static::swap($spy);
            });
        }
    }

    /**
     * Initiate a partial mock on the facade.
     *
     * @return \Mockery\MockInterface
     */
    public static function partialMock()
    {
        $name = static::getFacadeAccessor();

        $mock = static::isMock()
            ? static::$resolvedInstance[$name]
            : static::createFreshMockInstance();

        return $mock->makePartial();
    }

    /**
     * Initiate a mock expectation on the facade.
     *
     * @return \Mockery\Expectation
     */
    public static function shouldReceive()
    {
        $name = static::getFacadeAccessor();

        $mock = static::isMock()
            ? static::$resolvedInstance[$name]
            : static::createFreshMockInstance();

        return $mock->shouldReceive(...func_get_args());
    }

    /**
     * Initiate a mock expectation on the facade.
     *
     * @return \Mockery\Expectation
     */
    public static function expects()
    {
        $name = static::getFacadeAccessor();

        $mock = static::isMock()
            ? static::$resolvedInstance[$name]
            : static::createFreshMockInstance();

        return $mock->expects(...func_get_args());
    }

    /**
     * Create a fresh mock instance for the given class.
     *
     * @return \Mockery\MockInterface
     */
    protected static function createFreshMockInstance()
    {
        return tap(static::createMock(), function ($mock) {
            static::swap($mock);

            $mock->shouldAllowMockingProtectedMethods();
        });
    }

    /**
     * Create a fresh mock instance for the given class.
     *
     * @return \Mockery\MockInterface
     */
    protected static function createMock()
    {
        $class = static::getMockableClass();

        return $class ? Mockery::mock($class) : Mockery::mock();
    }

    /**
     * Determines whether a mock is set as the instance of the facade.
     *
     * @return bool
     */
    protected static function isMock()
    {
        $name = static::getFacadeAccessor();

        return isset(static::$resolvedInstance[$name]) &&
               static::$resolvedInstance[$name] instanceof LegacyMockInterface;
    }

    /**
     * Get the mockable class for the bound instance.
     *
     * @return string|null
     */
    protected static function getMockableClass()
    {
        if ($root = static::getFacadeRoot()) {
            return get_class($root);
        }
    }

    /**
     * Determines whether a "fake" has been set as the facade instance.
     *
     * @return bool
     */
    public static function isFake()
    {
        throw new NotImplementedException('this method is yet to be implemented');

        $name = static::getFacadeAccessor();

        return isset(static::$resolvedInstance[$name]) &&
               static::$resolvedInstance[$name] instanceof Fake;
    }

    /**
     * push the facade default aliases store instance
     * 
     * @return Arrayable
     */
    public static function pushDefaultAliases(array $aliases = [])
    {
        // static::$defaultAliases = isset(static::$defaultAliases) ? static::$defaultAliases->push($aliases) : new Arrayable($aliases);
    }

    /**
     * Get the application instance behind the facade.
     * 
     */
    public static function getFacadeApplication(): ?Application
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
        if (isset(static::$app)) {
            static::$app->instance(static::getFacadeAccessor(), $instance);
        }

        static::$resolvedInstance[static::getFacadeAccessor()] = $instance;
    }

    /**
     * Resolve the facade root instance from the container.
     *
     * @param  string  $accessor
     * @return mixed
     */
    protected static function resolveFacadeInstance($accessor)
    {
        if ($accessor === 'app')
            return static::getFacadeApplication();

        if (isset(static::$resolvedInstance[$accessor])) {
            return static::$resolvedInstance[$accessor];
        }

        if (static::$app) {
            if (static::$cached) {
                return static::$resolvedInstance[$accessor] = static::$app[$accessor];
            }

            return static::$app[$accessor];
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

    /**
     * Get the application default aliases.
     *
     * @return \Eyika\Atom\Framework\Support\Collections\Collection
     */
    public static function defaultAliases()
    {
        return new Collection([
            'App' => App::class,
            // 'Arr' => Arr::class,
            // 'Artisan' => Artisan::class,
            // 'Auth' => Auth::class,
            'Blade' => Blade::class,
            'Broadcast' => Broadcast::class,
            // 'Bus' => Bus::class,
            'Cache' => Cache::class,
            'Console' => Console::class,
            // 'Concurrency' => Concurrency::class,
            // 'Config' => Config::class,
            // 'Context' => Context::class,
            // 'Cookie' => Cookie::class,
            // 'Crypt' => Crypt::class,
            'DatabaseConnection' => DatabaseConnection::class,
            'Date' => Date::class,
            // 'Eloquent' => Model::class,
            // 'Event' => Event::class,
            'File' => File::class,
            // 'Gate' => Gate::class,
            // 'Hash' => Hash::class,
            'Http' => Http::class,
            // 'Js' => Js::class,
            // 'Lang' => Lang::class,
            // 'Log' => Log::class,
            // 'Mail' => Mail::class,
            // 'Notification' => Notification::class,
            // 'Number' => Number::class,
            // 'Password' => Password::class,
            // 'Process' => Process::class,
            // 'Queue' => Queue::class,
            // 'RateLimiter' => RateLimiter::class,
            // 'Redirect' => Redirect::class,
            'JsonResponse' => JsonResponse::class,
            'Request' => Request::class,
            'Response' => Response::class,
            // 'Route' => Route::class,
            'Schedule' => Scheduler::class,
            // 'Schema' => Schema::class,
            'Session' => Session::class,
            'Storage' => Storage::class,
            // 'Str' => Str::class,
            // 'URL' => URL::class,
            // 'Uri' => Uri::class,
            // 'Validator' => Validator::class,
            // 'View' => View::class,
            // 'Vite' => Vite::class,
        ]);
    }

    public static function __callStatic($method, $arguments)
    {
        $instance = static::getFacadeRoot();

        if (! $instance) {
            throw new RuntimeException('A facade root has not been set.');
        }

        if (!method_exists($instance, $method)) {
            if (static::$callStaticIfServiceMethodNotFound && method_exists($instance, "__callStatic")) {
                $baseclass = get_class($instance);
                return $baseclass::__callStatic($method, $arguments);
            } elseif (method_exists($instance, "__call")) {
                return $instance->__call($method, $arguments);
            }
            throw new BaseException("Method $method does not exist on the underlying service ". static::getFacadeAccessor(). ".");
        }

        return $instance->$method(...$arguments);
    }
}
