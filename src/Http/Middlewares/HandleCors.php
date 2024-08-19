<?php

namespace Eyika\Atom\Framework\Http\Middlewares;

use Eyika\Atom\Framework\Http\Request;
use Eyika\Atom\Framework\Http\Contracts\MiddlewareInterface;

class HandleCors implements MiddlewareInterface
{
    /**
     * List of allowed origins.
     *
     * @var array
     */
    protected $allowedOrigins = ['*']; // You can specify specific origins here

    /**
     * List of allowed HTTP methods.
     *
     * @var array
     */
    protected $allowedMethods = ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'];

    /**
     * List of allowed headers.
     *
     * @var array
     */
    protected $allowedHeaders = ['Content-Type', 'Authorization', 'X-Requested-With'];

    /**
     * List of exposed headers.
     *
     * @var array
     */
    protected $exposedHeaders = [];

    /**
     * Whether to allow credentials.
     *
     * @var bool
     */
    protected $allowCredentials = false;

    /**
     * Handle an incoming request.
     *
     */
    public function handle(Request $request): bool
    {
        if ($this->isCorsRequest($request)) {
            if ($this->isPreflightRequest($request)) {
                return $this->handlePreflight($request);
            }

            return $this->addCorsHeaders($request);
        }

        return true;
    }

    /**
     * Determine if the request is a CORS request.
     *
     * @param Request $request
     * @return bool
     */
    protected function isCorsRequest(Request $request): bool
    {
        return $request->headers->has('Origin') &&
            $request->headers->get('Origin') !== $request->getSchemeAndHttpHost();
    }

    /**
     * Determine if the request is a preflight request.
     *
     * @param Request $request
     * @return bool
     */
    protected function isPreflightRequest(Request $request): bool
    {
        return $request->isMethod('OPTIONS') &&
            $request->headers->has('Access-Control-Request-Method');
    }

    /**
     * Handle a preflight CORS request.
     *
     * @param Request $request
     */
    protected function handlePreflight(Request $request)
    {
        return $this->addCorsHeaders($request);
    }

    /**
     * Add CORS headers to the response.
     *
     * @param Request $request
     * @return void
     */
    protected function addCorsHeaders(Request $request)
    {
        $_origin = $request->headers->get('Origin');
        $origin = $this->allowedOrigins[0] === '*' ? '*' : $_origin;
        $allowedMethods = implode(', ', $this->allowedMethods);
        $allowedHeaders = implode(', ', $this->allowedHeaders);

        header("Access-Control-Allow-Origin: $origin");
        header("Access-Control-Allow-Methods: $allowedMethods");
        header("Access-Control-Allow-Headers: $allowedHeaders");

        if (!empty($this->exposedHeaders)) {
            $exposedHeaders = implode(', ', $this->exposedHeaders);
            header("Access-Control-Expose-Headers: $exposedHeaders");
        }

        if ($this->allowCredentials) {
            header("Access-Control-Allow-Credentials: true");
        }
    }
}
