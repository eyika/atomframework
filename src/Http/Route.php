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
        $previousPrefix = self::$groupPrefix;
        self::$groupPrefix = rtrim(self::$groupPrefix, '/') . '/' . ltrim($prefix, '/');

        if (count(self::$lastGroupMiddleware)) {
            $previousMiddlewares = self::$middlewares;
            self::$middlewares = count(self::$lastGroupMiddleware) > 1 && is_string(self::$lastGroupMiddleware[0]) ?
                [ ...self::$middlewares, self::$lastGroupMiddleware ] :
                array_merge(self::$middlewares, self::$lastGroupMiddleware);

            self::$lastGroupMiddleware = [];
            call_user_func($method);

            self::$middlewares = $previousMiddlewares;
            self::$groupPrefix = $previousPrefix;
            return new static();
        }

        call_user_func($method);

        self::$groupPrefix = $previousPrefix;
        return new static();
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
            [ ...self::$middlewares, $middleware ] :
            array_merge(self::$middlewares, $middleware);

        call_user_func($method);

        self::$middlewares = $previousMiddlewares;
        return new static();
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
        if (! self::$instantiated)
            new static;

        $requestMethod = $request->method();
        $requestUri = rtrim(filter_var($request->server('REQUEST_URI'), FILTER_SANITIZE_URL), '/');
        $requestUri = strtok($requestUri, '?');

        for (;;) {
            if (($middleware = array_shift(static::$defaultMiddlewares)) === '*') {
                break;
            }

            $middlewares = explode(':', $middleware);
            $middleware = array_shift($middlewares);
            $params = explode(',', $middlewares[0] ?? '');
            $middlewareInstance = new $middleware;

            if (method_exists($middlewareInstance, 'handle') && $status = $middlewareInstance->handle($request, ...$params)) {
                if ($status)
                    return true;
            }
        }

        // if ($request->isOptions()) {
        $keys = [];
        foreach (static::$defaultMiddlewares as $key => $middleware) {
            $_middleware = explode('\\', $middleware);
            $_middleware = strtolower($_middleware[count($_middleware) - 1]);
            if (in_array($_middleware, ['handlecors', 'servepublicassets'])) {
                $keys[] = $key;
                $middlewares = explode(':', $middleware);
                $middleware = array_shift($middlewares);
                $params = explode(',', $middlewares[0] ?? '');
                $middlewareInstance = new $middleware;
    
                if (method_exists($middlewareInstance, 'handle') && $status = $middlewareInstance->handle($request, ...$params)) {
                    if ($status)
                        return true;
                }
            }
        }
        foreach($keys as $key) {
            unset(static::$defaultMiddlewares[$key]);
        }
        // }

        foreach (self::$routes[$requestMethod] ?? [] as $route => $data) {
            $routeParts = explode('/', $route);
            $requestUriParts = explode('/', $requestUri);

            if (count($routeParts) != count($requestUriParts)) {
                $is_optional = false;
                foreach ($routeParts as $key => $part) {
                    if (preg_match("/^{[^}]*\?}$/", $part, $matches)) {
                        $is_optional = true;
                    }
                }
                if (!$is_optional)
                    continue;
            }

            $parameters = [];
            $matched = true;

            for ($i = 0; $i < count($requestUriParts); $i++) {
                if (preg_match("/^{([^}]+)\??}$/", $routeParts[$i], $matches)) {
                    $routePart = $matches[1];
                    $parameters[$routePart] = $requestUriParts[$i];
                } elseif ($routeParts[$i] != $requestUriParts[$i]) {
                    $matched = false;
                    break;
                }
            }

            $request->route_params = Arr::wrap(sanitize_data($parameters));

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
                        $resp = call_user_func_array($callback, array_merge([$request], is_array($parameters) ? array_values($parameters) : []));
                    } elseif (is_array($callback) && count($callback) > 1) {
                        [$controller, $method] = $callback;
                        $controllerInstance = new $controller;
                        $resp = call_user_func_array([$controllerInstance, $method], array_merge([$request], is_array($parameters) ? array_values($parameters) : []));
                    } elseif (is_string($callback)) {
                        $resp = include_once __DIR__ . "/$callback";
                    } else {
                        throw new NotFoundHttpException('route not found');
                    }
                // }

                if (is_string($resp)) {
                    echo $resp;
                    return true;
                }
                return true;
            }
        }

        if (isset(self::$routes['ANY']['/404'])) {
            $callback = self::$routes['ANY']['/404']['callback'];
            if (is_callable($callback)) {
                $resp = call_user_func($callback, $request);
            } elseif (is_array($callback) && count($callback) > 1) {
                [$controller, $method] = $callback;
                $controllerInstance = new $controller;
                $resp = call_user_func([$controllerInstance, $method], $request);
            } elseif (is_string($callback)) {
                $resp = include_once __DIR__ . "/$callback";
            } else {
                throw new NotFoundHttpException('requested resource not found');
            }
        }
        if (is_string($resp)) {
            echo $resp;
            return true;
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

    public static function set_csrf()
    {
        if (!isset($_SESSION["csrf"])) {
            $_SESSION["csrf"] = bin2hex(random_bytes(50));
        }
        echo '<input type="hidden" name="csrf" value="' . $_SESSION["csrf"] . '">';
    }

    public static function isApiRequest(bool|null $value = null)
    {
        if ($value === null) {
            return static::$apiRequest;
        }
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
