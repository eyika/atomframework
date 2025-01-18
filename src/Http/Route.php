<?php

namespace Eyika\Atom\Framework\Http;

use Eyika\Atom\Framework\Exceptions\Http\NotFoundHttpException;
use Eyika\Atom\Framework\Support\Arr;

class Route
{
    protected static $routes = [];
    public static $middlewares = [];
    public static $defaultMiddlewares = [];
    public static $middlewareAliases = [];
    public static $middlewarePriority = [];
    protected static $groupPrefix = '';
    protected static $groupDomains = [];
    protected static $routeName = '';
    protected static $currentRoute = '';
    private static $instantiated = false;
    private static $lastInsertedRouteKeys = '';
    private static $apiRequest = false;
    private static array $lastGroupMiddleware = [];

    public function __construct()
    {
        static::$instantiated = true;
    }

    public static function group(string $prefix, callable $method): self
    {
        return static::_group($prefix, $method);
    }

    public static function middleware(string | array $middleware, callable|false|null $method = null): self
    {
        $middleware = Arr::wrap($middleware);

        if ($method === null) {
            if (self::$lastInsertedRouteKeys !== '') {
                [$last_key, $last_value] = explode(' ::: ', self::$lastInsertedRouteKeys);

                self::$routes[$last_key][$last_value]['middlewares'] = // [...self::$routes[$last_key][$last_value]['middlewares'], $middleware];
                    count($middleware) > 1 && is_string($middleware[0]) ?
                    [...self::$routes[$last_key][$last_value]['middlewares'], $middleware] :
                    array_merge(self::$routes[$last_key][$last_value]['middlewares'], $middleware);
            }

            return new static();
        }

        if ($method === false) {
            self::$lastGroupMiddleware = $middleware;
            return new static();
        }

        $previousMiddlewares = self::$middlewares;
        self::$middlewares = count($middleware) > 1 && is_string($middleware[0]) ?
            [...self::$middlewares, $middleware] :
            array_merge(self::$middlewares, $middleware);

        call_user_func($method);

        self::$middlewares = $previousMiddlewares;
        return new static();
    }

    public static function domain(string | array $domain, callable $method): self
    {
        return static::_group(Arr::wrap($domain), $method, true);
    }

    public static function name(string $name, callable|null $method = null): self
    {
        $previousName = self::$routeName;
        self::$routeName = $name;

        if ($method === null) {
            if (self::$lastInsertedRouteKeys !== '') {
                [$last_key, $last_value] = explode(' ::: ', self::$lastInsertedRouteKeys);
                self::$routes[$last_key][$last_value]['name'] = $name;
                self::$routeName = $previousName;
            }

            return new static();
        }

        call_user_func($method);

        self::$routeName = $previousName;
        return new static();
    }

    protected static function addRoute(string $method, string $route, callable|string|array $path_to_include): self
    {
        // $slash = static::$apiRequest ? "/api/" : '/';
        $slash = '/';
        $route = self::$groupPrefix . $slash . ltrim($route, $slash);
        $route = rtrim($route, $slash);
        $name = self::$routeName ? self::$routeName : $route;

        self::$routes[$method][$route] = [
            'callback' => $path_to_include,
            'middlewares' => self::$middlewares,
            'name' => $name,
            'domains' => self::$groupDomains
        ];
        self::$lastInsertedRouteKeys = "$method ::: $route";

        self::$routeName = '';
        return new static();
    }

    public static function get(string $route, callable|string|array $path_to_include): self
    {
        return self::addRoute('GET', $route, $path_to_include);
    }

    public static function post(string $route, callable|string|array $path_to_include): self
    {
        return self::addRoute('POST', $route, $path_to_include);
    }

    public static function put(string $route, callable|string|array $path_to_include): self
    {
        return self::addRoute('PUT', $route, $path_to_include);
    }

    public static function patch(string $route, callable|string|array $path_to_include): self
    {
        return self::addRoute('PATCH', $route, $path_to_include);
    }

    public static function delete(string $route, callable|string|array $path_to_include): self
    {
        return self::addRoute('DELETE', $route, $path_to_include);
    }

    public static function any(string $route, callable|string|array $path_to_include): self
    {
        return self::addRoute('ANY', $route, $path_to_include);
    }

    public static function custom(string $method, string $route, callable|string|array $path_to_include): self
    {
        return self::addRoute($method, $route, $path_to_include);
    }

    public static function dispatch(Request $request)
    {
        // Initialize routes and ensure instantiation
        url()->setRoutes(self::$routes);
        if (!self::$instantiated) {
            new static;
        }
    
        $requestMethod = $request->method();
        $requestUri = rtrim(filter_var($request->requestUri(), FILTER_SANITIZE_URL), '/');
        $requestUri = strtok($requestUri, '?');
    
        // Find matching route and set route parameters
        $parameters = [];
        $routeMatched = false;
    
        foreach (self::$routes[$requestMethod] ?? [] as $route => $data) {
            if (self::matchesRoute($route, $requestUri, $parameters)) {
                $request->route_params = Arr::wrap(sanitize_data($parameters));
                self::$currentRoute = $route;
                $routeMatched = true;
                break;
            }
        }
    
        // If no route matches, set up a 404 handler
        if (!$routeMatched) {
            return self::handleNotFound($request)->send();
        }
    
        // Set up the middleware pipeline
        $middlewares = array_merge(
            static::$defaultMiddlewares,
            static::findRouteMiddlewares($requestMethod, $requestUri)
        );
    
        // Core handler for the pipeline
        $coreHandler = function ($request) use ($requestMethod, $requestUri) {
            $callback = self::$routes[$requestMethod][self::$currentRoute]['callback'];
            return self::executeCallback($callback, $request, $request->route_params ?? []);
        };
    
        // Run the pipeline
        $response = (new Pipeline())
            ->through($middlewares)
            ->then($coreHandler)
            ->run($request);
    
        // Output the response
        if ($response instanceof BaseResponse)
            return $response->send();
        else
            echo $response;
            return true;
    }

    public static function route($name, $parameters = [])
    {
        foreach (self::$routes as $method => $routes) {
            foreach ($routes as $route => $data) {
                if ($data['name'] === $name) {
                    foreach ($parameters as $key => $value) {
                        $route = str_replace('$' . $key, $value, $route);
                    }
                    return empty($route) ? '/' : $route;
                }
            }
        }

        return null;
    }

    public static function current($fullpath = true)
    {
        return url()->current($fullpath);
    }

    public static function storeCurrent()
    {
        url()->storeCurrent();
    }

    public static function previous(bool $store = false)
    {
        return url()->previous($store);
    }

    public static function out($text, bool $strip_tags = false)
    {
        if ($strip_tags) {
            echo htmlspecialchars(strip_tags($text));
        } else {
            echo htmlspecialchars($text);
        }
    }

    public static function isApiRequest(bool|null $value = null)
    {
        if ($value === null) {
            return static::$apiRequest;
        }
        static::$apiRequest = $value;
    }

    private static function domainIsValid(Request $request, $data)
    {
        if (empty($data['domains']))
            return true;
        return Arr::exists($data['domains'], $request->host());
    }

    private static function domainNotValid(Request $request, $data)
    {
        return !self::domainIsValid($request, $data);
    }

    private static function _group(string | array $prefix, callable $method, $is_domain = false): self
    {
        if ($is_domain) {
            self::$groupDomains = $prefix;
        } else {
            $previousPrefix = self::$groupPrefix;
            self::$groupPrefix = rtrim(self::$groupPrefix, '/') . '/' . ltrim($prefix, '/');
        }

        if (count(self::$lastGroupMiddleware)) {
            $previousMiddlewares = self::$middlewares;
            self::$middlewares = count(self::$lastGroupMiddleware) > 1 && is_string(self::$lastGroupMiddleware[0]) ?
                [...self::$middlewares, self::$lastGroupMiddleware] :
                array_merge(self::$middlewares, self::$lastGroupMiddleware);

            self::$lastGroupMiddleware = [];
            call_user_func($method);

            self::$middlewares = $previousMiddlewares;
            if ($is_domain)
                self::$groupDomains = [];
            else
                self::$groupPrefix = $previousPrefix;
            return new static();
        }

        call_user_func($method);

        if ($is_domain)
            self::$groupDomains = [];
        else
            self::$groupPrefix = $previousPrefix;

        return new static();
    }

    // Helper method to find middlewares for a specific route
    protected static function findRouteMiddlewares($requestMethod, $requestUri)
    {
        foreach (self::$routes[$requestMethod] ?? [] as $route => $data) {
            if (self::matchesRoute($route, $requestUri)) {
                return $data['middlewares'] ?? [];
            }
        }
        return [];
    }

    // Helper method to check if a route matches the request URI
    protected static function matchesRoute($route, $requestUri, &$parameters = [])
    {
        $routeParts = explode('/', $route);
        $requestUriParts = explode('/', $requestUri);

        if (count($routeParts) !== count($requestUriParts)) {
            if (!self::routeHasOptionalParts($routeParts)) {
                return false;
            }
        }

        $parameters = [];
        for ($i = 0; $i < count($requestUriParts); $i++) {
            if (preg_match("/^{([^}]+)\??}$/", $routeParts[$i], $matches)) {
                $parameters[$matches[1]] = $requestUriParts[$i];
            } elseif ($routeParts[$i] !== $requestUriParts[$i]) {
                return false;
            }
        }
        return true;
    }

    // Helper method to determine if a route contains optional parts
    protected static function routeHasOptionalParts($routeParts)
    {
        foreach ($routeParts as $part) {
            if (preg_match("/^{[^}]*\?}$/", $part)) {
                return true;
            }
        }
        return false;
    }

    // Execute the callback for a matched route
    protected static function executeCallback($callback, $request, $parameters)
    {
        if (is_callable($callback)) {
            return call_user_func_array($callback, array_merge([$request], array_values($parameters)));
        } elseif (is_array($callback) && count($callback) > 1) {
            [$controller, $method] = $callback;
            $controllerInstance = new $controller;
            return call_user_func_array([$controllerInstance, $method], array_merge([$request], array_values($parameters)));
        } elseif (is_string($callback)) {
            return include_once __DIR__ . "/$callback";
        } else {
            throw new NotFoundHttpException('Route not found');
        }
    }

    // Handle 404 Not Found
    protected static function handleNotFound($request)
    {
        if (isset(self::$routes['ANY']['/404'])) {
            $callback = self::$routes['ANY']['/404']['callback'];
            return self::executeCallback($callback, $request, []);
        }

        throw new NotFoundHttpException('Requested resource not found');
    }
}
