<?php

namespace Eyika\Atom\Framework\Http\Middlewares;

use Eyika\Atom\Framework\Http\Request;
use Eyika\Atom\Framework\Http\Contracts\MiddlewareInterface;
use Eyika\Atom\Framework\Support\Arr;

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

    protected $maxAge = 0;

    /**
     * Whether to allow credentials.
     *
     * @var bool
     */
    protected $allowCredentials = false;

    public function __construct()
    {
        $this->allowedOrigins = config('cors.allowed_origins');
        $this->allowedMethods = config('cors.allowed_methods');
        $this->allowedHeaders = config('cors.allowed_headers');
        $this->exposedHeaders = config('cors.exposed_headers');
        $this->allowCredentials = config('cors.supports_credentials');
        $this->maxAge =  config('cors.max_age');
    }

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

            return !$this->addCorsHeaders($request);
        }

        return false;
    }

    /**
     * Determine if the request is a CORS request.
     *
     * @param Request $request
     * @return bool
     */
    protected function isCorsRequest(Request $request): bool
    {
        return $request->hasHeader('Origin') &&
            $request->headers('Origin') !== $request->getSchemeAndHttpHost();
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
            $request->hasHeader('Access-Control-Request-Method');
    }

    /**
     * Handle a preflight CORS request.
     *
     * @param Request $request
     * @return bool
     */
    protected function handlePreflight(Request $request)
    {
        header("Content-Type: text/html; charset=utf-8", 200);
        $this->addCorsHeaders($request);
        return true;
    }

    /**
     * Add CORS headers to the response.
     *
     * @param Request $request
     * @return bool
     */
    protected function addCorsHeaders(Request $request)
    {
        $_origin = $request->headers('Origin');
        if ($this->allowedOrigins[0] === '*') {
            $origin = $_origin;
        } else if (Arr::exists($this->allowedOrigins, $_origin)) {
           $origin = $_origin;
        } else return false;

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
        return true;
    }
}
