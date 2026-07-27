<?php

namespace Eyika\Atom\Framework\Foundation\Concerns;

use Closure;
use Eyika\Atom\Framework\Exceptions\BaseException;
use ReflectionClass;

trait ServiceContainer
{
    use ClassDependencyResolver;

    protected $bindings = [];
    protected $instances = [];
    protected $aliases = [];
    protected $resolved = [];

    /** abstract => deferred provider class, registered lazily on first make() (PKG-04). */
    protected $deferredServices = [];

    // Bind a service to the container
    public function bind(string $key, $resolver): void
    {
        $this->bindings[$key] = $resolver;
    }

    /**
     * Determine if the given abstract type has been bound.
     *
     * @param  string  $abstract
     * @return bool
     */
    public function bound($abstract)
    {
        return isset($this->bindings[$abstract]) ||
               isset($this->instances[$abstract]) ||
               $this->isAlias($abstract);
    }

    /**
     * {@inheritdoc}
     *
     * @return bool
     */
    public function has(string $id): bool
    {
        return $this->bound($id);
    }

    /**
     * Determine if a given string is an alias.
     *
     * @param  string  $name
     * @return bool
     */
    public function isAlias($name)
    {
        return isset($this->aliases[$name]);
    }

    // Bind a singleton service to the container. The resolver is invoked once
    // (memoized) and RECEIVES the container so it can resolve its own deps.
    public function singleton(string $key, $resolver): void
    {
        $this->bindings[$key] = function ($app = null) use ($resolver) {
            static $instance;

            if ($instance === null) {
                $instance = is_callable($resolver) ? $resolver($app) : new $resolver;
            }

            return $instance;
        };
    }

    public function instance(string $accessor, mixed $instance): mixed
    {
        $this->instances[$accessor] = $instance;

        return $instance;
    }

    // Resolve a service and its dependencies.
    //  - instance(): returned from $instances (shared).
    //  - singleton(): its resolver self-memoizes, so make() returns the same object.
    //  - bind() / auto-resolved: TRANSIENT — a fresh object each call. (Previously
    //    make() cached EVERY resolution into $instances, silently turning bind() and
    //    auto-resolved classes into de-facto singletons.)
    public function make(string $key): mixed
    {
        $key = $this->getAlias($key);

        // A deferred provider's service is bound on first resolution (PKG-04): register
        // the owning provider now, then fall through to the freshly-created binding.
        if (isset($this->deferredServices[$key]) && !isset($this->instances[$key]) && !isset($this->bindings[$key])) {
            $this->loadDeferredService($key);
        }

        if (isset($this->instances[$key])) {
            return $this->instances[$key];
        }

        if (isset($this->bindings[$key])) {
            return $this->bindings[$key]($this);
        }

        return $this->resolve($key);
    }

    /**
     * Register the deferred provider that supplies $key. No-op in the base container;
     * the Application overrides this to register + boot the provider on demand.
     */
    protected function loadDeferredService(string $key): void
    {
        // overridden by Application (PKG-04)
    }

    // ---------------------------------------------------------------------------
    // Container niceties (PKG-07)
    // ---------------------------------------------------------------------------

    protected $tags = [];

    /** Register an alias so make($alias) resolves to $abstract. */
    public function alias(string $abstract, string $alias): void
    {
        if ($alias !== $abstract) {
            $this->aliases[$alias] = $abstract;
        }
    }

    /** Follow the alias chain to the concrete key (cycle-guarded). */
    protected function getAlias(string $abstract): string
    {
        $seen = [];
        while (isset($this->aliases[$abstract])) {
            if (isset($seen[$abstract])) {
                throw new BaseException("Container alias cycle detected for [$abstract].");
            }
            $seen[$abstract] = true;
            $abstract = $this->aliases[$abstract];
        }

        return $abstract;
    }

    /**
     * Decorate a resolved service (decorator pattern). The closure receives the
     * resolved instance and the container, and returns the (possibly wrapped) service.
     */
    public function extend(string $abstract, Closure $closure): void
    {
        $abstract = $this->getAlias($abstract);

        if (isset($this->instances[$abstract])) {
            $this->instances[$abstract] = $closure($this->instances[$abstract], $this);
            return;
        }

        if (isset($this->bindings[$abstract])) {
            $resolver = $this->bindings[$abstract];
            $this->bindings[$abstract] = fn ($app = null) => $closure($resolver($app ?? $this), $this);
            return;
        }

        // Unbound: decorate a fresh auto-resolution. resolve() does not re-enter the
        // binding lookup, so binding it here cannot recurse.
        $this->bindings[$abstract] = function () use ($abstract, $closure) {
            return $closure($this->resolve($abstract), $this);
        };
    }

    /** Assign one or more abstracts to one or more tags. */
    public function tag($abstracts, $tags): void
    {
        foreach ((array) $tags as $tag) {
            foreach ((array) $abstracts as $abstract) {
                $this->tags[$tag][] = $abstract;
            }
        }
    }

    /** Resolve every abstract registered under a tag. */
    public function tagged($tag): array
    {
        $resolved = [];
        foreach ($this->tags[$tag] ?? [] as $abstract) {
            $resolved[] = $this->make($abstract);
        }

        return $resolved;
    }

    /**
     * Invoke a callable, resolving its parameters from the container; entries in
     * $parameters (by name) take precedence over autowiring. Accepts a Closure,
     * "Class@method", [object, 'method'], a function name, or an invokable object.
     */
    public function call($callback, array $parameters = [])
    {
        if (is_string($callback) && str_contains($callback, '@')) {
            [$class, $method] = explode('@', $callback, 2);
            $callback = [$this->make($class), $method];
        }

        if (is_array($callback)) {
            $reflector = new \ReflectionMethod($callback[0], $callback[1]);
        } elseif (is_object($callback) && !$callback instanceof Closure) {
            $reflector = new \ReflectionMethod($callback, '__invoke');
        } else {
            $reflector = new \ReflectionFunction($callback);
        }

        $args = [];
        foreach ($reflector->getParameters() as $param) {
            $name = $param->getName();
            if (array_key_exists($name, $parameters)) {
                $args[] = $parameters[$name];
                continue;
            }

            $type = $param->getType();
            if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                try {
                    $args[] = $this->make($type->getName());
                    continue;
                } catch (\Throwable $e) {
                    // fall through to defaults below
                }
            }

            if ($param->isVariadic()) {
                continue;
            }
            if ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
            } elseif ($type && $type->allowsNull()) {
                $args[] = null;
            } else {
                throw new BaseException("Cannot resolve parameter \${$name} for call().");
            }
        }

        return $callback(...$args);
    }

    // ---------------------------------------------------------------------------
    // Worker-safety: scoped bindings + reset (WRK-09)
    // ---------------------------------------------------------------------------

    /** @var string[] Keys whose resolved instance is request-scoped (flushed per request). */
    protected $scopedInstances = [];

    /**
     * Bind a REQUEST-SCOPED service: resolved once and memoized (like a singleton),
     * but dropped by forgetScopedInstances() between requests under a worker — so
     * request-bound services (auth user, current request/response) don't leak.
     */
    public function scoped(string $key, $resolver): void
    {
        if (!in_array($key, $this->scopedInstances, true)) {
            $this->scopedInstances[] = $key;
        }

        $this->bindings[$key] = function ($app = null) use ($key, $resolver) {
            $app = $app ?? $this;
            if (!array_key_exists($key, $this->instances)) {
                $this->instances[$key] = is_callable($resolver) ? $resolver($app) : new $resolver;
            }
            return $this->instances[$key];
        };
    }

    /** Drop every request-scoped instance so the next request re-resolves them (WRK-09). */
    public function forgetScopedInstances(): void
    {
        foreach ($this->scopedInstances as $key) {
            unset($this->instances[$key]);
        }
    }

    /** Drop a single resolved instance. */
    public function forgetInstance(string $key): void
    {
        unset($this->instances[$key]);
    }

    /** Full container reset (worker shutdown / tests) — clears ALL bindings + state. */
    public function flush(): void
    {
        $this->bindings = [];
        $this->instances = [];
        $this->aliases = [];
        $this->resolved = [];
        $this->scopedInstances = [];
        $this->tags = [];
        $this->deferredServices = [];
    }

    /**
     * Determine if a given offset exists.
     *
     * @param  string  $key
     * @return bool
     */
    public function offsetExists($key): bool
    {
        return $this->bound($key);
    }

    /**
     * Get the value at a given offset.
     *
     * @param  string  $key
     * @return mixed
     */
    public function offsetGet($key): mixed
    {
        return $this->make($key);
    }

    /**
     * Set the value at a given offset.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return void
     */
    public function offsetSet($key, $value): void
    {
        $this->bind($key, $value instanceof Closure ? $value : fn () => $value);
    }

    /**
     * Unset the value at a given offset.
     *
     * @param  string  $key
     * @return void
     */
    public function offsetUnset($key): void
    {
        unset($this->bindings[$key], $this->instances[$key], $this->resolved[$key]);
    }

    /**
     * Dynamically access container services.
     *
     * @param  string  $key
     * @return mixed
     */
    public function __get($key)
    {
        return $this[$key];
    }

    /**
     * Dynamically set container services.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return void
     */
    public function __set($key, $value)
    {
        $this[$key] = $value;
    }
}
