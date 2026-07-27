<?php

namespace Eyika\Atom\Framework\Foundation\Contracts;

use ArrayAccess;

interface ApplicationInterface extends ArrayAccess
{
    // Bind a service to the container
    public function bind(string $key, $resolver): void;

    // Bind a singleton service to the container
    public function singleton(string $key, $resolver): void;

    // Resolve a service and its dependencies
    public function make(string $key): mixed;

    // Swap or set an instance
    public function instance(string $accessor, mixed $instance): mixed;

    // --- Container niceties (PKG-07) ---

    /** Register an alias so make($alias) resolves to $abstract. */
    public function alias(string $abstract, string $alias): void;

    /** Decorate a resolved service. */
    public function extend(string $abstract, \Closure $closure): void;

    /** Assign one or more abstracts to one or more tags. */
    public function tag($abstracts, $tags): void;

    /** Resolve every abstract registered under a tag. @return array */
    public function tagged($tag): array;

    /** Invoke a callable resolving its parameters from the container. */
    public function call($callback, array $parameters = []);

    // --- Worker-safety: scoped bindings + reset (WRK-09) ---

    /** Bind a request-scoped service (memoized, flushed between requests). */
    public function scoped(string $key, $resolver): void;

    /** Drop every request-scoped instance. */
    public function forgetScopedInstances(): void;

    /** Drop a single resolved instance. */
    public function forgetInstance(string $key): void;

    /** Full container reset (clears all bindings + state). */
    public function flush(): void;

    /** Reset all per-request state so a worker doesn't leak between requests. */
    public function flushRequestState(): void;
}
