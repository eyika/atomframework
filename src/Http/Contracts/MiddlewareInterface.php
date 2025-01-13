<?php

namespace Eyika\Atom\Framework\Http\Contracts;

use Closure;
use Eyika\Atom\Framework\Http\Request;
use Eyika\Atom\Framework\Http\Response;

interface MiddlewareInterface
{
    public function handle(Request $request, Closure $next): Response|string;
}
