<?php

namespace Eyika\Atom\Framework\Http;

/**
 * A top-level route group whose route file is loaded only when its matcher accepts
 * the current request. Registered from a RouteServiceProvider so the APP owns how
 * requests are wired to route files (and can add as many map types as it wants),
 * instead of the Server hardcoding the web-vs-api decision.
 *
 *   Route::map('api')->middleware('api')->stateless()
 *       ->when(fn(Request $r) => $r->wantsJson())
 *       ->load(base_path('routes/api.php'));
 *
 * A map with no when() matcher is a fallback (matches any request); list it last.
 */
class RouteMap
{
    protected string $name;
    protected ?string $file = null;
    protected ?string $middleware = null;
    protected mixed $matcher = null;   // callable(Request): bool
    protected bool $stateless = false;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    /** Middleware group (a key in the Kernel's middleware groups) applied to this map. */
    public function middleware(string $group): self
    {
        $this->middleware = $group;
        return $this;
    }

    /** Predicate deciding whether this map handles the request. */
    public function when(callable $matcher): self
    {
        $this->matcher = $matcher;
        return $this;
    }

    /**
     * Mark the map as stateless (API-like): no "previous URL" session write on GET.
     * Stateful maps (the default) behave like web routes.
     */
    public function stateless(bool $stateless = true): self
    {
        $this->stateless = $stateless;
        return $this;
    }

    /** The route file this map loads. Terminal builder call. */
    public function load(string $file): self
    {
        $this->file = $file;
        return $this;
    }

    /** Does this map accept the given request? A matcher-less map matches anything. */
    public function matches(Request $request): bool
    {
        if ($this->matcher === null) {
            return true;
        }

        return (bool) ($this->matcher)($request);
    }

    public function isFallback(): bool
    {
        return $this->matcher === null;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getFile(): ?string
    {
        return $this->file;
    }

    public function getMiddleware(): ?string
    {
        return $this->middleware;
    }

    public function isStateless(): bool
    {
        return $this->stateless;
    }
}
