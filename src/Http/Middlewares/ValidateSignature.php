<?php

namespace Eyika\Atom\Framework\Http\Middlewares;

use Closure;
use Eyika\Atom\Framework\Exceptions\Http\AccessDeniedHttpException;
use Eyika\Atom\Framework\Exceptions\NotImplementedException;
use Eyika\Atom\Framework\Http\Request;
use Eyika\Atom\Framework\Http\Contracts\MiddlewareInterface;
use Eyika\Atom\Framework\Http\Response;

class ValidateSignature implements MiddlewareInterface
{
    /**
     * Handle an incoming request.
     *
     * @throws AccessDeniedHttpException
     */
    public function handle(Request $request, Closure $next): Response|string
    {
        throw new NotImplementedException();
        return $next($request);
    }
}