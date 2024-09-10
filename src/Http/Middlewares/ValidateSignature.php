<?php

namespace Eyika\Atom\Framework\Http\Middlewares;

use Eyika\Atom\Framework\Exceptions\Http\AccessDeniedHttpException;
use Eyika\Atom\Framework\Exceptions\NotImplementedException;
use Eyika\Atom\Framework\Http\Request;
use Eyika\Atom\Framework\Http\Contracts\MiddlewareInterface;

class ValidateSignature implements MiddlewareInterface
{
    /**
     * Handle an incoming request.
     *
     * @throws AccessDeniedHttpException
     */
    public function handle(Request $request): bool
    {
        throw new NotImplementedException();
        return false;
    }
}