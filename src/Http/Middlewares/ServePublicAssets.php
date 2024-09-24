<?php

namespace Eyika\Atom\Framework\Http\Middlewares;

use Eyika\Atom\Framework\Http\Request;
use Eyika\Atom\Framework\Http\Contracts\MiddlewareInterface;
use Eyika\Atom\Framework\Support\Str;

class ServePublicAssets implements MiddlewareInterface
{
    /**
     * Handle an incoming request.
     *
     */
    public function handle(Request $request): bool
    {
        logger()->info('got to serve public');
        $server = strtolower($request->server('SERVER_SOFTWARE', ''));
    
    
        if (in_array($_ENV['APP_ENV'], [ 'local', 'dev' ]) && !str_contains($server, 'apache') && !str_contains($server, 'nginx') && (!str_contains($server, 'litespeed'))) {
            $customMappings = [
                'js' => 'text/javascript', //'application/javascript',
                'css' => 'text/css',
                'woff2' => 'font/woff2',
                'woff' => 'font/woff'
            ];

            $uri = explode('?', $_SERVER["REQUEST_URI"])[0];
            if (preg_match('/\.(?:js|css|svg|ico|woff|woff2|ttf|webp|pdf|png|jpg|json|jpeg|gif|md)$/', $uri)) {
                // logger()->info('got here 0' . $_SERVER["REQUEST_METHOD"]);
                $path = public_path().$uri;
                if (file_exists($path)) {
                    // logger()->info('got here 1' . $_SERVER["REQUEST_METHOD"]);
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

        return false;
    }
}
