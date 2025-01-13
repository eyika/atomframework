<?php

namespace Eyika\Atom\Framework\Http\Middlewares;

use Closure;
use Eyika\Atom\Framework\Http\Request;
use Eyika\Atom\Framework\Http\Contracts\MiddlewareInterface;
use Eyika\Atom\Framework\Http\Response;
use Eyika\Atom\Framework\Support\Str;

class ServePublicAssets implements MiddlewareInterface
{
    /**
     * Handle an incoming request.
     *
     */
    public function handle(Request $request, Closure $next): Response|string
    {
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
                $path = public_path().$uri;
                if (file_exists($path)) {
                    $mime = mime_content_type($path);
                    $ext = pathinfo($path, PATHINFO_EXTENSION);
                    if (array_key_exists($ext, $customMappings)) {
                        $mime = $customMappings[$ext];
                    }
                    return Response::setHeader("Content-Type", $mime, Response::STATUS_OK)->body(file_get_contents($path));
                }

                return Response::html("File Not Found", Response::STATUS_NOT_FOUND);
            }
        }

        return $next($request);
    }
}
