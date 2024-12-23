<?php

namespace Eyika\Atom\Framework\Http\Middlewares;

use Eyika\Atom\Framework\Exceptions\Db\ModelNotFoundException;
use Eyika\Atom\Framework\Http\Contracts\MiddlewareInterface;
use Eyika\Atom\Framework\Http\Request;
use Eyika\Atom\Framework\Support\Arr;
use Eyika\Atom\Framework\Support\Database\Contracts\ModelInterface;
use Eyika\Atom\Framework\Support\Database\Contracts\UserModelInterface;
use Eyika\Atom\Framework\Support\NamespaceHelper;

class SubstituteBindings implements MiddlewareInterface
{
    /**
     * Handle an incoming request.
     *
     * @throws NotFoundHttpException
     */
    public function handle(Request $request, ...$ignoreKeys): bool
    {
        // Get the route parameters from the request
        $routeParams = $request->route_params;

        if (empty($routeParams))
            return false;

        // Substitute bindings for each parameter
        foreach ($routeParams as $key => $value) {
            if (Arr::exists($ignoreKeys, $key))
                continue;

            if (is_numeric($value)) {
                $value = sanitize_data($value);
                // Example: replace `{user}` with an instance of User model
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

        return false;
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
        // Map route parameter keys to model classes
        $fullPath = base_path('app/Models');

        $namespace = project_namespace();

        $model_class = null;

        NamespaceHelper::loadAndPerformActionOnClasses($namespace, $fullPath, function (string $class_name, string $model) use (&$model_class, $key) {
            if ($key === strtolower($class_name)) {
                $model_class = $model;
                return true;
            }
        }, 'app');

        return $model_class;
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
