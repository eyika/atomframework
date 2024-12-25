<?php

namespace Eyika\Atom\Framework\Http;

use Dotenv\Dotenv;
use Exception;
use Eyika\Atom\Framework\Foundation\Application;
use Eyika\Atom\Framework\Foundation\Console\Scheduler;
use Eyika\Atom\Framework\Foundation\Contracts\ExceptionHandler;
use Eyika\Atom\Framework\Foundation\Contracts\Kernel;
use Eyika\Atom\Framework\Support\Encrypter;
use Eyika\Atom\Framework\Support\Facade\Facade;
use Eyika\Atom\Framework\Support\Storage\File;
use Eyika\Atom\Framework\Support\Storage\Storage;

class Server
{
    public static Application $app;
    protected const ignore_facades = ['console', 'app', 'application'];
    protected const facadables = [
        'encrypter' => Encrypter::class,
        'file' => File::class,
        'storage' => Storage::class,
        'request' => Request::class,
        'scheduler' => Scheduler::class
    ];

    public function __construct(Application $app)
    {
        static::$app = $app;

        Facade::setFacadeApplication($app);

        static::loadFacades();
    }

    public static function handle(): bool
    {
        try {
            $request = new Request();
            static::$app->instance('request', $request);
            if (preg_match('/^.*$/i', $request->getRequestUri())) {
                //register controllers
                if (!str_contains($request->getPathInfo(), '/api') && !$request->wantsJson() && !$request->isXmlHttpRequest() && !$request->isOptions()) {
                    static::loadMiddlewares('web');
                    ///TODO: load all default web middlewares
                    require_once base_path().'/routes/web.php';
                } else {
                    Route::isApiRequest(true);
                    static::loadMiddlewares('api');
                    ///TODO: load all default api middlewares
                    require_once base_path().'/routes/api.php';
                }
                $status = Route::dispatch($request);
                Route::storeCurrent();
                return $status;
            } else {
                return false; // Let php bultin server serve
            }
        } catch (Exception $e) {
            /** @var ExceptionHandler $handler */
            $handler = static::$app->make(ExceptionHandler::class);

            return $handler->render($request, $e);
        }
    }

    private static function loadMiddlewares(string $type)
    {
        /** @var Kernel $kernel */
        $kernel = static::$app->make(Kernel::class);

        Route::$middlewareAliases = $kernel->getMiddlewareAliases();
        $middlewares = $kernel->getMiddlewares();

        array_push($middlewares, '*', ...$kernel->getMiddlewareGroups()[$type]);
        Route::$defaultMiddlewares = $middlewares;

        Route::$middlewarePriority = $kernel->getMiddlewarePriority();
    }

    private static function loadFacades()
    {
        try {
            foreach (self::facadables as $tag => $class_name) {
                $facade_obj = new $class_name;

                static::$app->instance($tag, $facade_obj);
            }
        } catch (Exception $e) {
            logger()->info("INTERNAL: ".$e->getMessage(), $e->getTrace());
            ///TODO handle exception
        }
    }
}
