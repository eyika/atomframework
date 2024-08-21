<?php

namespace Eyika\Atom\Framework\Foundation;

use Eyika\Atom\Framework\Foundation\Contracts\Kernel as ContractsKernel;

class Kernel implements ContractsKernel
{
    /**
     * The application's global HTTP middleware stack.
     *
     * These middleware are run during every request to your application.
     *
     * @var array
     */
    protected $middleware = [
    ];

    /**
     * The application's route middleware groups.
     *
     * @var array
     */
    protected $middlewareGroups = [
        'web' => [
        ],

        'api' => [
        ],
    ];

    /**
     * The application's middleware aliases.
     *
     * Aliases may be used to conveniently assign middleware to routes and groups.
     *
     * @var array
     */
    protected $middlewareAliases = [
    ];

    /**
     * The priority-sorted list of middleware.
     *
     * This forces non-global middleware to always be in the given order.
     *
     * @var array
     */
    protected $middlewarePriority = [
    ];

    public function getMiddlewares(): array
    {
        return $this->middleware;
    }

    public function getMiddlewareGroups(): array
    {
        return $this->middlewareGroups;
    }

    public function getMiddlewareAliases(): array
    {
        return $this->middlewareAliases;
    }

    public function getMiddlewarePriority(): array
    {
        return $this->middlewarePriority;
    }
}
