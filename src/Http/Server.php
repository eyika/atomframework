<?php

namespace Eyika\Atom\Framework\Http;

use Exception;
use Eyika\Atom\Framework\Foundation\Application;
use Eyika\Atom\Framework\Foundation\Console\Scheduler;
use Eyika\Atom\Framework\Foundation\Contracts\ExceptionHandler;
use Eyika\Atom\Framework\Foundation\Contracts\Kernel;
use Eyika\Atom\Framework\Support\Encrypter;
use Eyika\Atom\Framework\Support\Facade\Facade;
use Eyika\Atom\Framework\Support\Storage\File;
use Eyika\Atom\Framework\Support\Storage\Storage;
use Throwable;

class Server
{
    public static Application $app;
    protected const ignore_facades = ['console', 'app', 'application'];
    protected const facadables = [
        // 'cache' => Cache::class,  // Already registered in Service Provider`
        'encrypter' => Encrypter::class,
        'hash' => \Eyika\Atom\Framework\Support\Hashing\Hasher::class,
        'file' => File::class,
        'request' => Request::class,
        'scheduler' => Scheduler::class,
        'session' => Session::class,
        'storage' => Storage::class,
        'response' => Response::class,
        'json_response' => JsonResponse::class,
        // 'blade' => Blade::class  // Already registered in Service Provider
    ];

    public function __construct(Application $app)
    {
        static::$app = $app;

        Facade::setFacadeApplication($app);

        static::loadFacades();
    }

    public static function handle(): bool
    {
        $request = null; // defined even if make('request') throws (used in catch)
        try {
            $request = static::$app->make('request');
            static::$app->instance('request', $request);
            static::$app->registerProviders();
            // ErrorHandler::register();
            // The guard here used to be preg_match('/^.*$/i', $request->requestUri()), which
            // matches every string (including the empty one) — so the condition was always
            // true and its `return false; // Let php builtin server serve` else-branch was
            // unreachable. Removed rather than left as a misleading suggestion that some
            // requests fall through to the built-in server (BUG-16).

            // Route wiring is owned by the app's RouteServiceProvider: each
            // Route::map() declares a matcher + middleware group + route file, and
            // the first map whose matcher accepts the request handles it (a
            // matcher-less map is the fallback). No matching map → no routes are
            // loaded and dispatch returns a not-found response.
            $map = Route::resolveMapFor($request);
            if ($map !== null && $map->getFile() !== null) {
                Route::isApiRequest($map->isStateless());
                if ($middleware = $map->getMiddleware()) {
                    static::loadMiddlewares($middleware);
                }
                Route::loadRoutesFile($map->getName(), $map->getFile());
            }

            $status = Route::dispatch($request);
                // Only remember the "previous URL" for web GET navigation. Storing it
                // for API, AJAX/JSON, or non-GET requests forced a needless session
                // write — a DB write under the MySQL session handler — on every
                // request, and overwriting it on POST is wrong anyway (PERF-18).
            if (!$request->isAssetRequest() && $request->isMethod('GET') && !Route::isApiRequest())
                Route::storeCurrent();

            return $status;
        } catch (Throwable $e) {
            /** @var ExceptionHandler $handler */
            $handler = static::$app->make(ExceptionHandler::class);

            return $handler->render($request, $e)->send();
        }
    }

    private static function loadMiddlewares(string $type)
    {
        /** @var Kernel $kernel */
        $kernel = static::$app->make(Kernel::class);

        Route::$middlewareAliases = $kernel->getMiddlewareAliases();
        $middlewares = $kernel->getMiddlewares();

        array_push($middlewares, ...$kernel->getMiddlewareGroups()[$type]);
        // array_push($middlewares, '*', ...$kernel->getMiddlewareGroups()[$type]);
        Route::$defaultMiddlewares = $middlewares;

        Route::$middlewarePriority = $kernel->getMiddlewarePriority();
    }

    private static function loadFacades()
    {
        // Bound lazily: each facade is constructed on first resolution, not on every boot.
        // Eagerly `new`-ing them meant an app paid for every facade whether or not it used
        // one, and — worse — a constructor that legitimately refuses to build (the Encrypter
        // rejecting an unset APP_KEY) surfaced as a boot-time error on unrelated requests.
        // singleton() still shares one instance per application, as instance() did.
        foreach (self::facadables as $tag => $class_name) {
            static::$app->singleton($tag, fn () => new $class_name);
        }
    }
}
