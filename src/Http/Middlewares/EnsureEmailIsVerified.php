<?php

namespace Eyika\Atom\Framework\Http\Middlewares;

use Eyika\Atom\Framework\Exceptions\Http\RequestException;
use Eyika\Atom\Framework\Http\Contracts\MiddlewareInterface;
use Eyika\Atom\Framework\Http\Request;

class EnsureEmailIsVerified implements MiddlewareInterface
{
    /**
     * Handle an incoming request.
     *
     * @throws RequestException
     */
    public function handle(Request $request): bool
    {
        // Assuming a method isEmailVerified() checks if the user's email is verified
        if (!$this->isEmailVerified($request)) {
            // Redirect to a verification notice page or show an error
            // You can customize this as needed
            throw new RequestException(403, 'Your email address is not verified.');
        }

        return true;
    }

    /**
     * Mock method to check if the user's email is verified.
     *
     * @param Request $request
     * @return bool
     */
    protected function isEmailVerified(Request $request): bool
    {
        // Example: Retrieve user from the request and check if their email is verified
        // This is a placeholder and should be replaced with actual user verification logic
        $user = $this->getUser($request);

        return $user && $user->email_verified_at !== null;
    }

    /**
     * Mock method to get the user from the request.
     *
     * @param Request $request
     * @return \Eyika\Atom\Framework\Support\Database\Contracts\UserModelInterface|null
     */
    protected function getUser(Request $request)
    {
        // Example: Retrieve the authenticated user from the request or session
        // This is a placeholder and should be replaced with actual user retrieval logic
        return $request->auth_user;
    }
}
