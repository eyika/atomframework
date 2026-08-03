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

            // Every value is a binding candidate, not just numeric ones (BUG-18): a UUID
            // primary key and a slug route key are both non-numeric, and the old is_numeric()
            // gate let them through as raw strings — so a controller typed against the model
            // silently received the URL segment instead.
            $model = $this->resolveModel($key, $value);

            // null = this parameter names no model at all (e.g. {format}); leave it as a scalar.
            if ($model === null) {
                continue;
            }

            if ($model) {
                $routeParams[$key] = $model;
            } else {
                throw new ModelNotFoundException("unable to retrieve $key with " . $this->routeKeyFor($key) . " $value");
            }
        }
        $request->route_params = $routeParams;

        return $next($request);
    }

    /**
     * Resolve a model instance for a route parameter.
     *
     * The three outcomes are deliberately distinct (BUG-19) — the caller cannot behave
     * correctly if "no such row" and "not a model parameter" look the same:
     *
     *   null   the parameter names no model at all ({format}, {page}) — leave it a scalar
     *   false  it names a model, but no row matched — the caller raises ModelNotFoundException
     *   model  bound
     *
     * The false case matters because find()/first() return NULL on a miss since BUG-23. Passing
     * that straight through made a missing row indistinguishable from a non-model parameter, so
     * the not-found path silently stopped firing and controllers received the raw URL segment.
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

        if (!class_exists($modelClass)) {
            return false;
        }

        $builder  = $modelClass::getBuilder();
        $routeKey = $this->routeKeyFor($key, $builder);

        // Binding by the primary key keeps the original find() path; a model that overrides
        // getRouteKeyName() (slug, uuid, …) is looked up on that column instead.
        $model = $routeKey === $builder->primaryKey
            ? $builder->find($value, false)
            : $builder->where($routeKey, $value)->first(false);

        // Normalise a miss (null or false, depending on the finder) to false.
        return $model ?: false;
    }

    /**
     * The column a route parameter binds against — the model's route key, or the primary key
     * when the parameter names no model (used only for the not-found message).
     */
    protected function routeKeyFor(string $key, $builder = null): string
    {
        if ($builder === null) {
            $modelClass = $this->modelClassForKey($key);

            if (!$modelClass || !class_exists($modelClass)) {
                return 'id';
            }

            $builder = $modelClass::getBuilder();
        }

        return method_exists($builder, 'getRouteKeyName')
            ? $builder->getRouteKeyName()
            : $builder->primaryKey;
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

        // An app with no app/Models directory is legitimate (an API with no route-model
        // binding, a fresh skeleton). Scanning it anyway threw UnexpectedValueException out
        // of RecursiveDirectoryIterator, so any route WITH a parameter 500'd.
        if (!is_dir($fullPath)) {
            return self::$modelMap = [];
        }

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
