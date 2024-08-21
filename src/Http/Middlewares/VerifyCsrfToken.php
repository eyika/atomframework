<?php

namespace Eyika\Atom\Framework\Http\Middlewares;

use Eyika\Atom\Framework\Exceptions\Http\AccessDeniedHttpException;
use Eyika\Atom\Framework\Http\Request;
use Eyika\Atom\Framework\Http\Contracts\MiddlewareInterface;

class VerifyCsrfToken implements MiddlewareInterface
{
    /**
     * Handle an incoming request.
     *
     * @throws AccessDeniedHttpException
     */
    public function handle(Request $request): bool
    {
        if ($this->shouldVerify($request)) {
            $this->verifyCsrfToken($request);
        }

        return false;
    }

    /**
     * Determine if the CSRF token should be verified for the request.
     *
     * @param Request $request
     * @return bool
     */
    protected function shouldVerify(Request $request): bool
    {
        // List of paths that should not have CSRF verification
        $except = [
            '/excluded-route', // Example path to exclude
        ];

        foreach ($except as $pattern) {
            if ($this->match($pattern, $request->getPathInfo())) {
                return false;
            }
        }

        return true;
    }

    /**
     * Verify the CSRF token for the request.
     *
     * @param Request $request
     * @return void
     * @throws AccessDeniedHttpException
     */
    protected function verifyCsrfToken(Request $request): void
    {
        $token = $request->header('X-CSRF-TOKEN') ?? $request->query('_token');

        if (!$this->isValidCsrfToken($token)) {
            throw new AccessDeniedHttpException('Invalid CSRF token.');
        }
    }

    /**
     * Check if the CSRF token is valid.
     *
     * @param string|null $token
     * @return bool
     */
    protected function isValidCsrfToken(?string $token): bool
    {
        // Retrieve the session token (this is just a placeholder; implement your own token retrieval)
        $sessionToken = $_SESSION['csrf_token'] ?? null;

        return hash_equals($sessionToken, $token);
    }

    /**
     * Match the path against a pattern.
     *
     * @param string $pattern
     * @param string $path
     * @return bool
     */
    protected function match(string $pattern, string $path): bool
    {
        // Simple wildcard matching
        return fnmatch($pattern, $path);
    }
}

// Example usage
// session_start();
// $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); // Generate a CSRF token for demonstration

// $request = Request::create('/some-route', 'POST', [], [], [], [
//     'HTTP_X_CSRF_TOKEN' => $_SESSION['csrf_token']
// ]);

// $middleware = new VerifyCsrfToken();

// try {
//     $response = $middleware->handle($request, function ($req) {
//         return new Response('CSRF token verified', 200);
//     });

//     $response->send();
// } catch (AccessDeniedHttpException $e) {
//     echo $e->getMessage();
// }
