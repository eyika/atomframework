<?php

namespace Eyika\Atom\Framework\Http\Contracts;

use Eyika\Atom\Framework\Http\Request;

interface MiddlewareInterface
{
    public function handle(Request $request): bool;
}
