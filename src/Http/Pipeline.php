<?php

namespace Eyika\Atom\Framework\Http;

use Closure;

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
        return function ($next, $pipe): callable {
            return function ($passable) use ($next, $pipe) {
                if (is_string($pipe)) {
                    // Resolve middleware class and parameters
                    [$pipeClass, $parameters] = $this->resolveMiddleware($pipe);
                    $pipe = new $pipeClass;
                } else {
                    $parameters = [];
                }

                // Call the middleware's handle method or invoke it
                return method_exists($pipe, 'handle')
                    ? $pipe->handle($passable, $next, ...$parameters)
                    : $pipe($passable, $next);
            };
        };
    }

    /**
     * Resolve middleware from a string definition.
     */
    protected function resolveMiddleware(string $pipe)
    {
        // Split the pipe string to extract class and parameters
        $parts = explode(':', $pipe, 2);
        $pipeClass = $parts[0];
        $parameters = isset($parts[1]) ? explode(',', $parts[1]) : [];
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
