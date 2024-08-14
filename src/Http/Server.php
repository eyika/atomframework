<?php

namespace Eyika\Atom\Framework\Http;

use Dotenv\Dotenv;
use Eyika\Atom\Framework\Foundation\Application;

class Server
{
    protected static Application $app;

    public function __construct(Application $app)
    {
        $this->$app = $app;
    }
    public static function handle(string $basepath): bool
    {
        $GLOBALS['basepath'] = $basepath;

        $dotenv = strtolower(PHP_OS_FAMILY) === 'windows' ? Dotenv::createImmutable(base_path()."\\") : Dotenv::createImmutable(base_path()."/");
        $dotenv->load();
        $dotenv->required([])->notEmpty(); ///TODO: get required env keys from config if set

        // Config::loadConfigFiles(base_path() . "/config");

        if (preg_match('/^.*$/i', $_SERVER["REQUEST_URI"])) {
            //register controllers
            require_once __DIR__.'/src/libs/routes.php';
        } else {
            return false; // Let php bultin server serve
        }
    }
}