<?php

namespace Eyika\Atom\Framework\Http\Middlewares;

use Eyika\Atom\Framework\Http\Request;
use Eyika\Atom\Framework\Http\Contracts\MiddlewareInterface;
use Eyika\Atom\Framework\Http\Response;

class PermissionMiddleware implements MiddlewareInterface
{
    protected array $permissions;

    public function __construct(array $permissions)
    {
        $this->permissions = $permissions;
    }

    /**
     * Handle an incoming request.
     *
     */
    public function handle(Request $request): bool
    {
        // Assuming a method getUserPermissions() returns an array of user permissions
        $userPermissions = $this->getUserPermissions($request);

        foreach ($this->permissions as $permission) {
            if (!in_array($permission, $userPermissions)) {
                return Response::plain('Forbidden', 403); // 403 Forbidden
            }
        }

        return false;
    }

    /**
     * Mock method to get user permissions from the request or user session.
     *
     * @param Request $request
     * @return array
     */
    protected function getUserPermissions(Request $request): array
    {
        // Example: Retrieve permissions from user session or authentication service
        // This is a placeholder and should be replaced with actual user permission retrieval logic
        return $request->user_permissions ?? [];
    }
}

// Example usage
// $request = Request::createFromGlobals();
// $permissions = ['view_dashboard', 'edit_settings']; // Permissions required for the route
// $middleware = new PermissionMiddleware($permissions);

// $response = $middleware->handle($request, function ($req) {
//     // Simulating a response
//     return new Response('Access granted', 200);
// });

// $response->send();
