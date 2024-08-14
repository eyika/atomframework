<?php

namespace Eyika\Atom\Framework\Http;

class Server
{
    public static function handle(): bool
    {
        if (preg_match('/^.*$/i', $_SERVER["REQUEST_URI"])) {
            //register controllers
            require_once __DIR__.'/src/libs/routes.php';
        } else {
            return false; // Let php bultin server serve
        }
    }
}