<?php

namespace Eyika\Atom\Framework\Http\Middlewares;

use Eyika\Atom\Framework\Exceptions\Http\RequestException as RequestException;
use Eyika\Atom\Framework\Http\Request;
use Eyika\Atom\Framework\Http\Contracts\MiddlewareInterface;

class RoleMiddleware implements MiddlewareInterface
{
    /**
     * The roles required to access the route.
     *
     * @var array
     */
    protected $roles;

    /**
     * Create a new middleware instance.
     *
     * @param array $roles
     */
    public function __construct(array $roles)
    {
        $this->roles = $roles;
    }

    /**
     * Handle an incoming request.
     *
     * @throws RequestException
     */
    public function handle(Request $request): bool
    {
        if (!$this->hasRequiredRole($request)) {
            // Return a 403 Forbidden response or redirect to an error page
            throw new RequestException(403, 'You do not have the required role to access this resource.');
        }

        return true;
    }

    /**
     * Check if the user has the required role(s).
     *
     * @param Request $request
     * @return bool
     */
    protected function hasRequiredRole(Request $request)
    {
        $user = $this->getUser($request);

        if (!$user) {
            return false; // No user, so no roles can be checked
        }

        // Assuming the user has a method roles() returning an array of roles
        $userRoles = $user->roles();

        // Check if the user has at least one of the required roles
        foreach ($this->roles as $role) {
            if (in_array($role, $userRoles)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Mock method to get the user from the request.
     *
     * @param Request $request
     * @return object|null
     */
    protected function getUser(Request $request)
    {
        // Example: Retrieve the authenticated user from the request or session
        return $request->attributes->get('user');
    }
}

// Example usage
// $request = Request::createFromGlobals();
// $middleware = new RoleMiddleware(['admin', 'editor']);

// $response = $middleware->handle($request, function ($req) {
//     // Simulating a response
//     return new Response('Access granted', 200);
// });

// $response->send();
