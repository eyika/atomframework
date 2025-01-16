<?php

namespace Eyika\Atom\Framework\Http\Middlewares;

use Closure;
use Eyika\Atom\Framework\Http\BaseResponse;
use Eyika\Atom\Framework\Http\Request;
use Eyika\Atom\Framework\Http\Contracts\MiddlewareInterface;
use Eyika\Atom\Framework\Support\Facade\Blade;

class ShareErrorsFromSession implements MiddlewareInterface
{
    /**
     * Handle an incoming request.
     *
     */
    public function handle(Request $request, Closure $next): BaseResponse
    {
        // Share errors with the view
        if ($request->hasSession()) {
            $this->shareErrors($request);
        }

        return $next($request);
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

        if ($session->has(BaseResponse::REQUEST_ERRORS_KEY)) {
            $errors = $session->get(BaseResponse::REQUEST_ERRORS_KEY);
            $this->shareWithViews($errors);
            $session->unset(BaseResponse::REQUEST_ERRORS_KEY);
        }
        if ($session->has(BaseResponse::REQUEST_VALIDATION_ERRORS_KEY)) {
            $errors = $session->get(BaseResponse::REQUEST_VALIDATION_ERRORS_KEY);
            $this->shareValidationWithViews($errors);
            $session->unset(BaseResponse::REQUEST_VALIDATION_ERRORS_KEY);
        }
        if ($session->has(BaseResponse::REQUEST_VALIDATION_ERRORS_KEY)) {
            $errors = $session->get(BaseResponse::REQUEST_VALIDATION_ERRORS_KEY);
            $this->shareValidationWithViews($errors);
            $session->unset(BaseResponse::REQUEST_VALIDATION_ERRORS_KEY);
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
        Blade::atomSetErrors($errors);
    }

    /**
     * Share validation errors with all views.
     *
     * @param $errors
     * @return void
     */
    protected function shareValidationWithViews($errors): void
    {
        Blade::atomSetValidationErrors($errors);
    }

    /**
     * Share old inputs with all views.
     *
     * @param $iputs
     * @return void
     */
    protected function shareInputWithViews($inputs): void
    {
        Blade::atomSetOldInputs($inputs);
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
