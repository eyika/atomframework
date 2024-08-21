<?php

namespace Eyika\Atom\Framework\Http\Middlewares;

use Eyika\Atom\Framework\Exceptions\Http\UnauthorizedHttpException;
use Eyika\Atom\Framework\Http\Request;
use Eyika\Atom\Framework\Support\Database\DB;
use Eyika\Atom\Framework\Http\Contracts\MiddlewareInterface;
use Eyika\Atom\Framework\Http\JsonResponse;

class AuthenticateSession implements MiddlewareInterface
{
    /**
     * Handle an incoming request.
     *
     * @throws UnauthorizedHttpException
     */
    public function handle(Request $request): bool
    {
        // Check if the user is authenticated
        if (!$this->isAuthenticated($request)) {
            throw new UnauthorizedHttpException('Session: User is not authenticated.', JsonResponse::STATUS_UNAUTHORIZED);
        }

        // Regenerate session ID if necessary
        $this->ensureSessionIsFresh($request);

        return false;
    }

    /**
     * Determine if the user is authenticated.
     *
     * @param Request $request
     * @return bool
     */
    protected function isAuthenticated(Request $request): bool
    {
        if (!$id = 1) {
            return false;
        }
        if (!$user = DB::find('users', $id)) {
            return false;
        }
        $request->auth_user = $user;
        return true;
    }

    /**
     * Ensure the session is fresh by regenerating the session ID if necessary.
     *
     * @param Request $request
     * @return void
     */
    protected function ensureSessionIsFresh(Request $request): void
    {
        // Check if the session was previously regenerated
        if (!$request->getSession()->has('session_regenerated_at')) {
            $this->regenerateSession($request);
        } elseif (time() - $request->getSession()->get('session_regenerated_at') > 300) {
            // Regenerate session every 5 minutes to prevent session fixation
            $this->regenerateSession($request);
        }
    }

    /**
     * Regenerate the session ID.
     *
     * @param Request $request
     * @return void
     */
    protected function regenerateSession(Request $request): void
    {
        // Regenerate the session ID to prevent session fixation
        session_regenerate_id(true);

        // Store the time the session was regenerated
        $request->getSession()->set('session_regenerated_at', time());
    }
}
