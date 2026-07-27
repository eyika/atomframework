<?php

namespace Eyika\Atom\Framework\Http\Middlewares;

use Closure;
use Eyika\Atom\Framework\Exceptions\Db\ModelNotFoundException;
use Eyika\Atom\Framework\Http\BaseResponse;
use Eyika\Atom\Framework\Http\Contracts\MiddlewareInterface;
use Eyika\Atom\Framework\Http\Request;
use Eyika\Atom\Framework\Support\Arr;
use Eyika\Atom\Framework\Support\Database\Contracts\ModelInterface;
use Eyika\Atom\Framework\Support\Database\Contracts\UserModelInterface;
use Eyika\Atom\Framework\Support\NamespaceHelper;

class SubstituteBindings implements MiddlewareInterface
{
    /**
     * Cached map of lowercased model short-name => FQCN, built once per process
     * (PERF-01 / BUG-17). Previously every route parameter of every request walked
     * the entire app/Models directory via NamespaceHelper. The model set is fixed
     * at runtime, so this is safe to memoize (including under long-lived workers).
     *
     * @var array<string,string>|null
     */
    protected static ?array $modelMap = null;

    /**
     * Handle an incoming request.
     *
     * @throws NotFoundHttpException
     */
    public function handle(Request $request, Closure $next, ...$ignoreKeys): BaseResponse
    {
        // Get the route parameters from the request
        $routeParams = $request->route_params;

        if (empty($routeParams))
            return $next($request);

        // Substitute bindings for each parameter
        foreach ($routeParams as $key => $value) {
            if (Arr::exists($ignoreKeys, $key))
                continue;

            if (is_numeric($value)) {
                // Route params are already sanitized at dispatch (Route::dispatch),
                // so there is no second sanitize_data() pass here (PERF-19).
                $model = $this->resolveModel($key, $value);
                if ($model === null) {
                    continue;
                }
                if ($model) {
                    $routeParams[$key] = $model;
                } else {
                    throw new ModelNotFoundException("unable to retrieve $key with id $value");
                }
            }
        }
        $request->route_params = $routeParams;

        return $next($request);
    }

    /**
     * Resolve a model instance based on the parameter key and value.
     *
     * @param string $key
     * @param mixed $value
     * @return ModelInterface|UserModelInterface|false|null
     */
    protected function resolveModel(string $key, $value): ModelInterface | UserModelInterface | false | null
    {
        // Map the route parameter to a model class
        if (!$modelClass = $this->modelClassForKey($key)) {
            return null;
        }

        if ($modelClass && class_exists($modelClass)) {
            return $modelClass::getBuilder()->find($value, false);
        }

        return false;
    }

    /**
     * Map a route parameter key to a model class.
     *
     * @param string $key
     * @return string|null
     */
    protected function modelClassForKey(string $key): ?string
    {
        return $this->modelMap()[$key] ?? null;
    }

    /**
     * Build (once) and return the lowercased-short-name => FQCN map for app/Models.
     * Memoized on a static so the directory is walked a single time per process
     * instead of once per route parameter per request (PERF-01).
     *
     * @return array<string,string>
     */
    protected function modelMap(): array
    {
        if (self::$modelMap !== null) {
            return self::$modelMap;
        }

        $map = [];
        $fullPath = base_path('app/Models');
        $namespace = project_namespace();

        NamespaceHelper::loadAndPerformActionOnClasses($namespace, $fullPath, function (string $class_name, string $model) use (&$map) {
            // Keyed by the lowercased short class name (matches the previous
            // `$key === strtolower($class_name)` comparison). Collect all — no
            // short-circuit — so the full map is cached.
            $map[strtolower($class_name)] = $model;
            return false;
        }, 'app');

        return self::$modelMap = $map;
    }

    /**
     * Reset the memoized model map (test isolation / worker reload).
     */
    public static function flushModelMap(): void
    {
        self::$modelMap = null;
    }
}

// Example usage
// $request = Request::create('/users/1', 'GET');
// $request->attributes->set('_route_params', [
//     'user' => 1,
// ]);

// $middleware = new SubstituteBindings();

// $response = $middleware->handle($request, function ($req) {
//     // Simulating a response
//     return new Response('Bindings substituted', 200);
// });

// $response->send();
