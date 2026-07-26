<?php

namespace Eyika\Atom\Framework\Support;

use Eyika\Atom\Framework\Support\Facade\Session;

class Url
{
    protected static $routes = [];

    /**
     * Store the application route definitions in Url instance
     */
    public static function setRoutes(array $routes)
    {
        static::$routes = $routes;
    }

    /**
     * Generate an absolute URL.
     *
     * @param string $path
     * @return string
     */
    public static function to($path = '')
    {
        // Get the protocol
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";

        // Get the host
        $host = $_SERVER['HTTP_HOST'];

        // Build the URL
        $url = $protocol . $host . '/' . ltrim($path, '/');

        return $url;
    }

    /**
     * Get the current URL.
     *
     * @return string
     */
    public static function current($fullpath = true)
    {
        $requestUri = $_SERVER['REQUEST_URI'];
        if (!$fullpath) {
            return $requestUri;
        }
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        $host = $_SERVER['HTTP_HOST'];
        return $protocol . $host . $requestUri;
    }

    /**
     * Store the current URL in the session.
     */
    public static function storeCurrent()
    {
        Session::set('previous_url', self::current());
    }

    /**
     * Retrieve the previous URL from the session.
     *
     * @return string|null
     */
    public static function previous(bool $store = false)
    {
        if ($store) {
            Session::set('previous_url', self::current());
            return;
        }
        return Session::get('previous_url');
    }

    public static function route($name, $parameters = [])
    {
        foreach (self::$routes as $method => $routes) {
            foreach ($routes as $route => $data) {
                if ($data['name'] === $name) {
                    foreach ($parameters as $key => $value) {
                        // Routes use {key}/{key?} placeholders, not $key.
                        $route = str_replace(['{' . $key . '}', '{' . $key . '?}'], $value, $route);
                    }
                    return $route;
                }
            }
        }

        return null;
    }

    private static function signingKey(): string
    {
        return (string) config('app.key');
    }

    /**
     * Canonical = path + sorted query (minus `signature`). Signer and validator
     * MUST build it identically (this matches Http\Request::validateSignature()).
     */
    private static function canonical(string $path, array $query): string
    {
        unset($query['signature']);
        ksort($query);
        return $path . (empty($query) ? '' : '?' . http_build_query($query));
    }

    public static function signedRoute($name, $parameters = [], $expiration = null)
    {
        $path = self::route($name, $parameters);
        if (!$path) {
            return $path;
        }

        $query = [];
        if ($expiration !== null) {
            $query['expires'] = (int) $expiration;
        }

        $query['signature'] = hash_hmac('sha256', self::canonical($path, $query), self::signingKey());

        return $path . '?' . http_build_query($query);
    }

    public static function temporarySignedRoute($name, $expiration, $parameters = [])
    {
        return self::signedRoute($name, $parameters, $expiration);
    }

    public static function validateSignature($url, $secret = null)
    {
        $parts = parse_url($url);
        $path = $parts['path'] ?? '/';
        parse_str($parts['query'] ?? '', $query);

        $signature = $query['signature'] ?? null;
        if (empty($signature)) {
            return false;
        }
        if (isset($query['expires']) && (int) $query['expires'] < time()) {
            return false;
        }

        $expected = hash_hmac('sha256', self::canonical($path, $query), $secret ?? self::signingKey());

        return hash_equals($expected, (string) $signature);
    }
}

// Store the current URL before the script ends
// Url::storeCurrentUrl();
