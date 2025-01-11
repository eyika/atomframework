<?php

namespace Eyika\Atom\Framework\Http\Middlewares;

use Exception;
use Eyika\Atom\Framework\Http\Request;
use Eyika\Atom\Framework\Http\Contracts\MiddlewareInterface;
use Eyika\Atom\Framework\Http\Session;
use Eyika\Atom\Framework\Support\Facade\Facade;
use Eyika\Atom\Framework\Support\Facade\Session as FacadeSession;

class StartSession  implements MiddlewareInterface
{
    /**
     * Handle an incoming request.
     *
     */
    public function handle(Request $request): bool
    {
        if (strtolower($_SERVER["REQUEST_METHOD"]) !== "options") {
            $this->startSession($request);
        }
        return false;
    }

    /**
     * Start the session for the request.
     *
     * @param Request $request
     * @return void
     */
    protected function startSession(Request $request)
    {
        if (!$request->hasSession()) {
            $session = $this->createSession();
            $request->setSession($session);
        }

        $request->getSession()->start();
    }

    /**
     * Save the session data after the response has been sent.
     *
     * @param Request $request
     * @return void
     */
    protected function saveSession(Request $request)
    {
        if ($request->hasSession()) {
            $session = $request->getSession();
            $session->save();
        }
    }

    /**
     * Create a new session instance.
     *
     * @return Session
     */
    protected function createSession()
    {
        try {
            return Facade::getFacadeApplication()->make('session');
        } catch (Exception $e) {
            if ($e->getMessage() == "Class session is not instantiable.") {
                return Facade::getFacadeApplication()->make(Session::class);
            }
        }
    }
}

// Example usage
// $request = Request::createFromGlobals();
// $middleware = new StartSession();

// $response = $middleware->handle($request, function ($req) {
//     // Further processing...
//     return new Response('Session started', 200);
// });

// $response->send();
