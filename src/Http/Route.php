<?php

namespace Eyika\Atom\Framework\Http;

use Eyika\Atom\Framework\Exceptions\Http\NotFoundHttpException;
use Eyika\Atom\Framework\Support\Arr;
use Eyika\Atom\Framework\Support\Facade\Response;

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

    /** @var RouteMap[] Request-routed maps registered by the RouteServiceProvider. */
    protected static array $maps = [];

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
        // Strip the query string BEFORE trimming the trailing slash. Doing it the other
        // way round makes the root URL "/" rtrim to "" and then strtok("", "?") returns
        // FALSE — so the homepage route (registered under "") never matched and every
        // "/" request 404'd.
        $requestUri = strtok(filter_var($request->requestUri(), FILTER_SANITIZE_URL), '?');
        $requestUri = rtrim($requestUri === false ? '' : $requestUri, '/');
    
        // Find matching route and set route parameters
        $parameters = [];
        $matched = null;

        // Try method-specific routes first, then method-agnostic ANY routes. The `+`
        // union keeps method routes first and winning on any path clash, so
        // Route::any() routes (filed under 'ANY') now actually dispatch.
        $candidates = (self::$routes[$requestMethod] ?? []) + (self::$routes['ANY'] ?? []);
        foreach ($candidates as $route => $data) {
            // Static routes (no "{param}") match by exact string equality — skip the
            // explode + per-segment preg_match that matchesRoute() runs (PERF-12).
            // The loop still iterates in registration order, so first-registered-wins
            // precedence (a static/dynamic route clash) is unchanged.
            if (strpos($route, '{') === false) {
                if ($route !== $requestUri) {
                    continue;
                }
                $parameters = [];
            } elseif (!self::matchesRoute($route, $requestUri, $parameters)) {
                continue;
            }

            $request->route_params = Arr::wrap(sanitize_data($parameters));
            self::$currentRoute = $route;
            $matched = $data;
            break;
        }

        // Reuse the matched route's own middlewares — no second full route scan.
        $middlewares = array_merge(
            static::$defaultMiddlewares,
            $matched['middlewares'] ?? []
        );

        // Core handler for the pipeline
        $coreHandler = function ($request) use ($matched) {
            // If no route matches, hand off to the not-found handler. Return its
            // result (don't ->send() here) so it flows through the same response
            // wrapping as a matched route below.
            if ($matched === null) {
                return self::handleNotFound($request);
            }
            return self::executeCallback($matched['callback'], $request, $request->route_params ?? []);
        };
    
        // Run the pipeline
        $response = (new Pipeline())
            ->through($middlewares)
            ->then($coreHandler)
            ->run($request);
    
        // Output the response
        if (!$response instanceof BaseResponse)
            $response = Response::plain(is_null($response) ? '' : (string)$response);

        return $response->send();
        // elseif (is_string($response)) {
        //     echo $response;
        //     return true;
        // } else return true;
    }

    public static function route($name, $parameters = [])
    {
        foreach (self::$routes as $method => $routes) {
            foreach ($routes as $route => $data) {
                if ($data['name'] === $name) {
                    foreach ($parameters as $key => $value) {
                        // Routes are declared with {key}/{key?} placeholders, not $key.
                        $route = str_replace(['{' . $key . '}', '{' . $key . '?}'], $value, $route);
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

    /**
     * Register a request-routed map (typically from a RouteServiceProvider) and return
     * it for fluent configuration. Maps are consulted in registration order — list
     * specific (matcher) maps first and the fallback (no when()) last.
     */
    public static function map(string $name): RouteMap
    {
        return self::$maps[$name] = new RouteMap($name);
    }

    /** All registered maps, in registration order. */
    public static function maps(): array
    {
        return self::$maps;
    }

    /**
     * The map that should handle $request: the first whose matcher accepts it, else
     * the first matcher-less fallback map, else null when no maps are registered (the
     * Server then uses its legacy web/api heuristic).
     */
    public static function resolveMapFor(Request $request): ?RouteMap
    {
        $fallback = null;
        foreach (self::$maps as $map) {
            if ($map->isFallback()) {
                $fallback ??= $map;
                continue;
            }
            if ($map->matches($request)) {
                return $map;
            }
        }

        return $fallback;
    }

    /** Reset the map registry (tests / worker reload). */
    public static function flushMaps(): void
    {
        self::$maps = [];
    }

    /**
     * Reset PER-REQUEST routing state (WRK-05) so a persistent worker doesn't carry a
     * previous request's route table / current-route / api flag into the next one.
     * The provider-registered maps are KEPT (they're wired once at boot); the Kernel
     * re-populates default middlewares each request.
     */
    public static function flushRequestState(): void
    {
        self::$routes = [];
        self::$currentRoute = '';
        self::$apiRequest = false;
        self::$groupPrefix = '';
        self::$groupDomains = [];
        self::$routeName = '';
        self::$lastInsertedRouteKeys = '';
        self::$middlewares = [];
        self::$lastGroupMiddleware = [];
        self::$defaultMiddlewares = [];
    }

    /** The full registered route table (used by route:cache). */
    public static function getRoutes(): array
    {
        return self::$routes;
    }

    /**
     * Restore a previously captured route table. A persistent worker loads its routes
     * once at boot (require_once runs the route file a single time), snapshots them with
     * getRoutes(), and restores that snapshot here after each request's flushRequestState
     * — so the immutable route table survives without re-requiring the (already-required)
     * source file.
     */
    public static function setRoutes(array $routes): void
    {
        self::$routes = $routes;
    }

    /**
     * Reset registration-time state so a route file can be (re)loaded cleanly when
     * compiling the cache. Leaves dispatch-time state (default middlewares, aliases,
     * priority) alone — that comes from the Kernel at request time.
     */
    public static function clearRegistered(): void
    {
        self::$routes = [];
        self::$groupPrefix = '';
        self::$groupDomains = [];
        self::$middlewares = [];
        self::$routeName = '';
    }

    /** Path to a route file's compiled cache artifact, or null if base_path is unavailable. */
    public static function routeCachePath(string $name): ?string
    {
        if (!function_exists('base_path')) {
            return null;
        }
        return base_path("bootstrap/cache/routes-{$name}.php");
    }

    /**
     * Load a route file into the table: from its compiled cache when present
     * (PERF-11), otherwise by requiring the source. A file containing closure routes
     * is never cached (see buildRouteCacheData), so it is always required and its
     * closures stay dynamically registered.
     */
    public static function loadRoutesFile(string $name, string $sourceFile): void
    {
        $cacheFile = self::routeCachePath($name);
        if ($cacheFile !== null && is_file($cacheFile)) {
            $cached = require $cacheFile;
            if (is_array($cached)) {
                foreach ($cached as $method => $routes) {
                    self::$routes[$method] = array_merge(self::$routes[$method] ?? [], $routes);
                }
                return;
            }
        }

        require_once $sourceFile;
    }

    /**
     * Compile a route file for caching: load it in isolation and return its route
     * table plus any closure routes found (closures can't be var_export'd, so a file
     * with closures is not cacheable). Restores a clean registration state after.
     *
     * @return array{routes: array, closures: string[]}
     */
    public static function buildRouteCacheData(string $sourceFile): array
    {
        self::clearRegistered();
        require $sourceFile;

        $routes = self::$routes;
        $closures = [];
        foreach ($routes as $method => $rs) {
            foreach ($rs as $uri => $data) {
                if (($data['callback'] ?? null) instanceof \Closure) {
                    $closures[] = "$method $uri";
                }
            }
        }

        self::clearRegistered();

        return ['routes' => $routes, 'closures' => $closures];
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
            // Request has more segments than the route → not a match (avoids an
            // undefined-index warning on $routeParts[$i]).
            if (!array_key_exists($i, $routeParts)) {
                return false;
            }
            // Capture the param NAME without the trailing "?" of an optional segment
            // (the old [^}]+ greedily captured "id?" for {id?}).
            if (preg_match('/^\{([^}?]+)\??\}$/', $routeParts[$i], $matches)) {
                // URL-decode route parameter values so things like "Simple%20RSI"
                // become "Simple RSI" before they reach controllers / DB queries.
                $parameters[$matches[1]] = rawurldecode($requestUriParts[$i]);
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
