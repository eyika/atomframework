<?php

namespace Eyika\Atom\Framework\Http\Middlewares;

use Eyika\Atom\Framework\Http\Request;
use Eyika\Atom\Framework\Http\Contracts\MiddlewareInterface;

class ShareErrorsFromSession implements MiddlewareInterface
{
    /**
     * Handle an incoming request.
     *
     */
    public function handle(Request $request): bool
    {
        // Share errors with the view
        if ($request->hasSession()) {
            $this->shareErrors($request);
        }

        return true;
    }

    /**
     * Share errors from the session with the view.
     *
     * @param Request $request
     * @return void
     */
    protected function shareErrors(Request $request): void
    {
        $session = $request->getSession();

        if ($session->has('errors')) {
            $errors = $session->get('errors');
            $this->shareWithViews($errors);
        }
    }

    /**
     * Share errors with all views.
     *
     * @param $errors
     * @return void
     */
    protected function shareWithViews($errors): void
    {
        // In Laravel, errors are typically shared with views via a view composer
        // Here, we'll simply output them for demonstration purposes
        echo "<pre>Error Details:</pre>";
        print_r($errors);
    }
}

// Example usage
// $request = Request::createFromGlobals();
// $middleware = new ShareErrorsFromSession();

// $response = $middleware->handle($request, function ($req) {
//     // Simulating a response where errors might be shared
//     return new Response('Errors shared from session', 200);
// });

// $response->send();
