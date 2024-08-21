<?php

namespace Eyika\Atom\Framework\Foundation\Contracts;

interface Kernel
{
    public function getMiddlewares(): array;

    public function getMiddlewareGroups(): array;

    public function getMiddlewareAliases(): array;

    public function getMiddlewarePriority(): array;
}
