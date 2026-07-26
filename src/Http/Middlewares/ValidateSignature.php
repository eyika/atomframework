<?php

namespace Eyika\Atom\Framework\Http\Middlewares;

use Closure;
use Eyika\Atom\Framework\Exceptions\Http\AccessDeniedHttpException;
use Eyika\Atom\Framework\Http\BaseResponse;
use Eyika\Atom\Framework\Http\Request;
use Eyika\Atom\Framework\Http\Contracts\MiddlewareInterface;

class ValidateSignature implements MiddlewareInterface
{
    /** Query params excluded from the signature (override per app as needed). */
    protected array $ignore = [];

    /**
     * Reject the request unless it carries a valid signature.
     *
     * @throws AccessDeniedHttpException
     */
    public function handle(Request $request, Closure $next): BaseResponse
    {
        if (!$request->hasValidSignatureWhileIgnoring($this->ignore)) {
            throw new AccessDeniedHttpException('Invalid signature.');
        }

        return $next($request);
    }
}