<?php

namespace Eyika\Atom\Framework\Http;

use Dotenv\Dotenv;
use Exception;
use Eyika\Atom\Framework\Foundation\Application;
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

        $request = Request::capture();
        if (preg_match('/^.*$/i', $request->getRequestUri())) {
            //register controllers
            if (strpos($request->getPathInfo(), '/api') === false) {
                ///TODO: load all default web middlewares
                require_once base_path().'/routes/web.php';
            } else {
                Route::isApiRequest(true);
                ///TODO: load all default api middlewares
                require_once base_path().'/routes/api.php';
            }
            return Route::dispatch($request);
        } else {
            return false; // Let php bultin server serve
        }
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
