<?php

namespace Eyika\Atom\Framework\Http\Middlewares;

use Eyika\Atom\Framework\Exceptions\Db\ModelNotFoundException;
use Eyika\Atom\Framework\Http\Contracts\MiddlewareInterface;
use Eyika\Atom\Framework\Http\Request;
use Eyika\Atom\Framework\Support\Database\Contracts\ModelInterface;
use Eyika\Atom\Framework\Support\Database\Contracts\UserModelInterface;

class SubstituteBindings implements MiddlewareInterface
{
    /**
     * Handle an incoming request.
     *
     * @throws NotFoundHttpException
     */
    public function handle(Request $request): bool
    {
        // Get the route parameters from the request
        $routeParams = $request->route_params;

        // Substitute bindings for each parameter
        foreach ($routeParams ?? [] as $key => $value) {
            if (is_numeric($value)) {
                // Example: replace `{user}` with an instance of User model
                $model = $this->resolveModel($key, $value);
                if ($model) {
                    $request->{$key} = $model;
                } else {
                    throw new ModelNotFoundException("Model not found for parameter: $key");
                }
            }
        }

        return false;
    }

    /**
     * Resolve a model instance based on the parameter key and value.
     *
     * @param string $key
     * @param mixed $value
     * @return ModelInterface|UserModelInterface|null
     */
    protected function resolveModel(string $key, $value): ModelInterface | UserModelInterface | null
    {
        // Map the route parameter to a model class
        $modelClass = $this->modelClassForKey($key);

        if ($modelClass && class_exists($modelClass)) {
            return $modelClass::find($value);
        }

        return null;
    }

    /**
     * Map a route parameter key to a model class.
     *
     * @param string $key
     * @return string|null
     */
    protected function modelClassForKey(string $key): ?string
    {
        // Map route parameter keys to model classes (customize as needed)
        $map = [
            'user' => 'App\Models\User', // Example mapping
            'post' => 'App\Models\Post', // Example mapping
        ];

        return $map[$key] ?? null;
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
