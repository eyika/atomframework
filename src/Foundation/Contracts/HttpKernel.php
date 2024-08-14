<?php

namespace Eyika\Atom\Framework\Foundation\Contracts;

interface HttpKernel
{
    /**
     * The application's global HTTP middleware stack.
     *
     * These middleware are run during every request to your application.
     *
     * @var array
     */
    public $middleware;

    /**
     * The application's route middleware groups.
     *
     * @var array
     */
    public $middlewareGroups;

    /**
     * The application's middleware aliases.
     *
     * Aliases may be used to conveniently assign middleware to routes and groups.
     *
     * @var array
     */
    public $middlewareAliases;

    /**
     * The priority-sorted list of middleware.
     *
     * This forces non-global middleware to always be in the given order.
     *
     * @var array
     */
    public $middlewarePriority;
}
