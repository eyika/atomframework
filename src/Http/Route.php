<?php

namespace Eyika\Atom\Framework\Http;

use Eyika\Atom\Framework\Exceptions\NotFoundException;
use Eyika\Atom\Framework\Support\Arr;

class Route
{
    protected static $routes = [];
    protected static $middlewares = [];
    public static $defaultMiddlewares = [];
    public static $middlewareAliases = [];
    public static $middlewarePriority = [];
    protected static $groupPrefix = '';
    protected static $routeName = '';
    protected static $currentRoute = '';
    private static $instantiated = false;
    private static $lastInsertedRouteKeys = '';
    private static $apiRequest = false;

    public function __construct()
    {
        static::$instantiated = true;
    }

    public static function group(string $prefix, callable $method): self
    {
        $previousPrefix = self::$groupPrefix;
        self::$groupPrefix = rtrim(self::$groupPrefix, '/') . '/' . ltrim($prefix, '/');

        call_user_func($method);

        self::$groupPrefix = $previousPrefix;
        return new static();
    }

    public static function middleware(string | array $middleware, callable $method = null): self
    {
        $middleware = Arr::wrap($middleware);

        if ($method === null) {
            if (self::$lastInsertedRouteKeys !== '') {
                [$last_key, $last_value] = explode(' ::: ', self::$lastInsertedRouteKeys);

                self::$routes[$last_key][$last_value]['middlewares'] =
                    count($middleware) > 1 && is_string($middleware[0]) ?
                        // self::$routes[$last_key][$last_value]['middlewares'] = [...self::$routes[$last_key][$last_value]['middlewares'], $middleware] :
                        [...self::$routes[$last_key][$last_value]['middlewares'], $middleware] :
                        array_merge(self::$routes[$last_key][$last_value]['middlewares'], $middleware);
            }

            return new static();
        }

        $previousMiddlewares = self::$middlewares;
        self::$middlewares = array_merge(self::$middlewares, $middleware);

        call_user_func($method);

        self::$middlewares = $previousMiddlewares;
        return new static();
    }

    public static function name(string $name, callable $method = null): self
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
        $route = self::$groupPrefix . $slash . ltrim($route, '/');
        $route = rtrim($route, '/');
        $name = self::$routeName ? self::$routeName : $route;

        self::$routes[$method][$route] = [
            'callback' => $path_to_include,
            'middlewares' => self::$middlewares,
            'name' => $name,
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

    public static function dispatch(Request $request)
    {
        url()->setRoutes(self::$routes);
        url()->storeCurrent();
        if (! self::$instantiated)
            new static;

        $requestMethod = $request->method();
        $requestUri = rtrim(filter_var($request->server('REQUEST_URI'), FILTER_SANITIZE_URL), '/');
        $requestUri = strtok($requestUri, '?');

        foreach (self::$routes[$requestMethod] ?? [] as $route => $data) {
            $routeParts = explode('/', $route);
            $requestUriParts = explode('/', $requestUri);

            if (count($routeParts) != count($requestUriParts)) {
                continue;
            }

            $parameters = [];
            $matched = true;

            for ($i = 0; $i < count($routeParts); $i++) {
                if (preg_match("/^[$]/", $routeParts[$i])) {
                    $routePart = ltrim($routeParts[$i], '$');
                    $parameters[$routePart] = $requestUriParts[$i];
                } elseif ($routeParts[$i] != $requestUriParts[$i]) {
                    $matched = false;
                    break;
                }
            }
            $request->route_params = $parameters;

            if ($matched) {
                self::$currentRoute = $route;

                foreach (static::$defaultMiddlewares as $key => $middleware) {
                    $middlewares = explode(':', $middleware);
                    $middleware = array_shift($middlewares);
                    $params = explode(',', $middlewares[0] ?? '');
                    $middlewareInstance = new $middleware;

                    if (method_exists($middlewareInstance, 'handle') && $status = $middlewareInstance->handle($request, ...$params)) {
                        if ($status)
                            return true;
                    }
                }

                foreach ($data['middlewares'] as $key => $middlewares) {
                    $params = null;
                    if (is_array($middlewares) && sizeof($middlewares) > 1) {
                        $middleware = array_shift($middlewares);
                        $params = explode(',', array_shift($middlewares));
                        if (array_key_exists($middleware, static::$middlewareAliases)) {
                            $middlewareInstance = new static::$middlewareAliases[$middleware];
                        } else {
                            $middlewareInstance = new $middleware;
                        }

                        if (method_exists($middlewareInstance, 'handle') && $status = $middlewareInstance->handle($request, ...$params)) {
                            if ($status)
                                return true;
                        }
                        continue;
                    }
                    $middlewares = Arr::wrap($middlewares)[0];
                    $middlewareInstance = new $middlewares;
                    if (method_exists($middlewareInstance, 'handle') && $status = $middlewareInstance->handle($request)) {
                        if ($status)
                            return true;
                    }
                }

                $parameters = $request->route_params;

                // foreach ($data['callback'] as $callback) {
                    $callback = $data['callback'];
                    if (is_callable($callback)) {
                        call_user_func_array($callback, array_merge([$request], is_array($parameters) ? array_values($parameters) : []));
                    } elseif (is_array($callback) && count($callback) > 1) {
                        [$controller, $method] = $callback;
                        $controllerInstance = new $controller;
                        call_user_func_array([$controllerInstance, $method], array_merge([$request], is_array($parameters) ? array_values($parameters) : []));
                    } elseif (is_string($callback)) {
                        include_once __DIR__ . "/$callback";
                    } else {
                        throw new NotFoundException('route not found');
                    }
                // }

                return true;
            }
        }

        if (isset(self::$routes['ANY']['/404'])) {
            $data = self::$routes['ANY']['/404'];
            // foreach ($data['callback'] as $callback) {
                $callback = $data['callback'];
                if (is_callable($callback)) {
                    call_user_func($callback, $request);
                } elseif (is_array($callback) && count($callback) > 1) {
                    [$controller, $method] = $callback;
                    $controllerInstance = new $controller;
                    call_user_func([$controllerInstance, $method], $request);
                } elseif (is_string($callback)) {
                    include_once __DIR__ . "/$callback";
                } else {
                    throw new NotFoundException('route not found');
                }
            // }
        }
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
                    return $route;
                }
            }
        }

        return null;
    }

    public static function current()
    {
        return url()->current();
    }

    public static function out($text, bool $strip_tags = false)
    {
        if ($strip_tags) {
            echo htmlspecialchars(strip_tags($text));
        } else {
            echo htmlspecialchars($text);
        }
    }

    public static function set_csrf()
    {
        if (!isset($_SESSION["csrf"])) {
            $_SESSION["csrf"] = bin2hex(random_bytes(50));
        }
        echo '<input type="hidden" name="csrf" value="' . $_SESSION["csrf"] . '">';
    }

    public static function isApiRequest(bool $value)
    {
        static::$apiRequest = $value;
    }

    public static function is_csrf_valid()
    {
        if (!isset($_SESSION['csrf']) || !isset($_POST['csrf'])) {
            return false;
        }
        if ($_SESSION['csrf'] != $_POST['csrf']) {
            return false;
        }
        return true;
    }
}
