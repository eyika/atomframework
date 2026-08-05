<?php

namespace Eyika\Atom\Framework\Http;

use Closure;
use Eyika\Atom\Framework\Exceptions\BaseException;

class Pipeline
{
    protected $passable;
    protected array $pipes = [];
    protected Closure $coreHandler;
    protected $parameters = [];

    /**
     * Set the pipes (middlewares) to process.
     */
    public function through(array $pipes)
    {
        $this->pipes = $pipes;
        return $this;
    }

    /**
     * Set parameters that can be shared across the pipeline.
     */
    public function withParameters(array $parameters)
    {
        $this->parameters = $parameters;
        return $this;
    }

    /**
     * Define the final core handler to call after the pipeline.
     */
    public function then(Closure $coreHandler)
    {
        $this->coreHandler = $coreHandler;
        return $this;
    }

    /**
     * Run the pipeline with the given passable object.
     * 
     * @return BaseResponse|string|bool
     */
    public function run($passable)
    {
        $this->passable = $passable;

        // Reduce the pipes to a single callable pipeline
        $pipeline = array_reduce(
            array_reverse($this->pipes),
            $this->carry(),
            $this->coreHandler
        );

        $resp = $pipeline($this->passable);

        return $resp;
    }

    /**
     * Get the callable that wraps middleware around the next handler.
     * Create the closure chain for the pipeline.
     *
     * @return callable
     */
    protected function carry(): callable
    {
        return function (Closure $next, string|array|callable $pipe): callable {
            return function (Request $passable) use ($next, $pipe) {
                if (is_string($pipe) || is_array($pipe)) {
                    // Resolve middleware class and parameters
                    [$pipeClass, $parameters] = $this->resolveMiddleware($pipe);
                    $pipe = is_callable($pipeClass) ? $pipeClass : new $pipeClass;
                } else {
                    $parameters = [];
                }

                // Call the middleware's handle method or invoke it
                $resp = method_exists($pipe, 'handle')
                    ? $pipe->handle($passable, $next, ...$parameters)
                    : $pipe($passable, $next);

                if (!$resp instanceof BaseResponse && !is_string($resp)) {
                    $pipename = getCallableName($pipe);
                    throw new BaseException("$pipename must return a BaseResponse object or string");
                }
                return $resp;
            };
        };
    }

    /**
     * Resolve middleware from a string definition to a class name and its arguments.
     *
     * `'throttle:auth-login,10,300'` → `[ThrottleRequests::class, ['auth-login', '10', '300']]`.
     *
     * The first segment is looked up in `Route::$middlewareAliases` (populated from the app
     * Kernel) before being treated as a class name. That lookup was previously absent entirely:
     * the raw segment was returned, so `carry()` reached `new 'auth'` and every route declaring
     * `->middleware('auth')` died with `Class "auth" not found`. The alias map was assigned by
     * `Server` and by the testing `TestCase` and read by nobody.
     */
    protected function resolveMiddleware(string|array $pipe)
    {
        // Split the pipe string to extract class and parameters
        $parts = is_array($pipe) ? $pipe : explode(':', $pipe, 2);
        $name = $parts[0];
        $parameters = isset($parts[1]) ? explode(',', $parts[1]) : [];

        $pipeClass = Route::$middlewareAliases[$name] ?? $name;

        if (is_string($pipeClass) && !class_exists($pipeClass) && !is_callable($pipeClass)) {
            // Name the actual mistake. A bare token that resolved to nothing is a missing Kernel
            // alias, and reporting it as `Class "auth" not found` sends you hunting for a file
            // that was never supposed to exist.
            if ($pipeClass === $name && !str_contains($name, '\\')) {
                throw new BaseException(
                    "Unknown middleware alias [$name] — add it to the \$middlewareAliases map in your Kernel, or reference the middleware by its class name."
                );
            }

            throw new BaseException("Middleware class \"$pipeClass\" not found.");
        }

        return [$pipeClass, $parameters];
    }

    // Helper method to format middlewares for the Pipeline
    // protected static function formatMiddlewares(array $middlewares): array
    // {
    //     return array_map(function ($middleware) {
    //         $parts = explode(':', $middleware);
    //         $middlewareClass = array_shift($parts);
    //         $params = isset($parts[0]) ? explode(',', $parts[0]) : [];
    //         return [$middlewareClass, $params];
    //     }, $middlewares);
    // }
}
