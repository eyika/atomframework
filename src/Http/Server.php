<?php

namespace Eyika\Atom\Framework\Http;

use Dotenv\Dotenv;
use Exception;
use Eyika\Atom\Framework\Foundation\Application;
use Eyika\Atom\Framework\Foundation\Contracts\Kernel;
use Eyika\Atom\Framework\Support\Facade\Facade;
use Eyika\Atom\Framework\Support\NamespaceHelper;
use Eyika\Atom\Framework\Support\Str;

class Server
{
    public static Application $app;
    protected const ignore_facades = ['console', 'app', 'application'];

    public function __construct(Application $app)
    {
        static::$app = $app;

        Facade::setFacadeApplication($app);

        static::loadFacades();
    }

    public static function handle(): bool
    {
        $dotenv = strtolower(PHP_OS_FAMILY) === 'windows' ? Dotenv::createImmutable(base_path()."\\") : Dotenv::createImmutable(base_path()."/");
        $dotenv->load();
        $dotenv->required([])->notEmpty(); ///TODO: get required env keys from config if set

        // Config::loadConfigFiles(base_path() . "/config");

        $server = strtolower($_SERVER['SERVER_SOFTWARE']) ?? "";


        if (in_array($_ENV['APP_ENV'], [ 'local', 'dev' ]) && (!str_contains($server, 'apache') && (!str_contains($server, 'nginx')) && (!str_contains($server, 'litespeed')))) {

            $customMappings = [
                'js' => 'text/javascript', //'application/javascript',
                'css' => 'text/css',
                'woff2' => 'font/woff2'
            ];

            if (preg_match('/\.(?:js|css|svg|ico|woff2|ttf|webp|pdf|png|jpg|json|jpeg|gif|md)$/', $_SERVER["REQUEST_URI"])) {
                $path = $_SERVER['DOCUMENT_ROOT'].$_SERVER["REQUEST_URI"];
                if (file_exists($path)) {
                    $mime = mime_content_type($path);
                    $ext = pathinfo($path, PATHINFO_EXTENSION);
                    if (array_key_exists($ext, $customMappings)) {
                        $mime = $customMappings[$ext];
                    }
                    header("Content-Type: $mime", true, 200);
                    echo file_get_contents($path);
                    return true;
                }

                header("Content-type: text/html", true, 404);
                echo "File Not Found";

                return true;
            }
        }

        $request = Request::capture();
        if (preg_match('/^.*$/i', $request->getRequestUri())) {
            //register controllers
            if (strpos($request->getPathInfo(), '/api') === false) {
                static::loadMiddlewares('web');
                ///TODO: load all default web middlewares
                require_once base_path().'/routes/web.php';
            } else {
                Route::isApiRequest(true);
                static::loadMiddlewares('api');
                ///TODO: load all default api middlewares
                require_once base_path().'/routes/api.php';
            }
            return Route::dispatch($request);
        } else {
            return false; // Let php bultin server serve
        }
    }

    private static function loadMiddlewares(string $type)
    {
        /** @var Kernel $kernel */
        $kernel = static::$app->make(Kernel::class);

        Route::$middlewareAliases = $kernel->getMiddlewareAliases();
        $middlewares = $kernel->getMiddlewares();

        array_push($middlewares, ...$kernel->getMiddlewareGroups()[$type]);
        Route::$defaultMiddlewares = $middlewares;

        Route::$middlewarePriority = $kernel->getMiddlewarePriority();
    }

    private static function loadFacades()
    {
        try {
            $ds = DIRECTORY_SEPARATOR;
            $fullPath = __DIR__ . $ds. "..". $ds. "Support". $ds. "Facade";
            $listObject = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($fullPath, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );

            $namespace = NamespaceHelper::getBaseNamespace();
    
            foreach ($listObject as $fileinfo) {
                if (!$fileinfo->isDir() && strtolower(pathinfo($fileinfo->getRealPath(), PATHINFO_EXTENSION)) == explode('.', '.php')[1]) {
                    $facade = classFromFile($fileinfo, $namespace);
                    $class_name = explode("\\", $facade);
                    $class_name = $class_name[count($class_name) - 1];
                    if (in_array(strtolower($class_name), static::ignore_facades))
                        continue;
    
                    $facade_obj = new $facade;
    
                    static::$app->instance(Str::camel($class_name), $facade_obj);
                }
            }
        } catch (Exception $e) {
            logger()->info("INTERNAL: ".$e->getMessage(), $e->getTrace());
            ///TODO handle exception
        }
    }
}
