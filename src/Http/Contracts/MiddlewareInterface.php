<?php

namespace Eyika\Atom\Framework\Http\Contracts;

use Closure;
use Eyika\Atom\Framework\Http\BaseResponse;
use Eyika\Atom\Framework\Http\Request;

interface MiddlewareInterface
{
    public function handle(Request $request, Closure $next): BaseResponse|string;
}
