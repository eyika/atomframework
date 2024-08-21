<?php

namespace Eyika\Atom\Framework\Support;

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
        $_SESSION['previous_url'] = self::current();
    }

    /**
     * Retrieve the previous URL from the session.
     *
     * @return string|null
     */
    public static function previous(bool $store = false)
    {
        if ($store) {
            $_SESSION['previous_url'] = self::current();
            return;
        }
        return isset($_SESSION['previous_url']) ? $_SESSION['previous_url'] : null;
    }

    public static function route($name, $parameters = [])
    {
        foreach (self::$routes as $method => $routes) {
            foreach ($routes as $route => $data) {
                if ($data['name'] === $name) {
                    foreach ($parameters as $key => $value) {
                        $route = str_replace('$' . $key, $value, $route);
                    }
                    return $route;
                }
            }
        }

        return null;
    }

    public static function signedRoute($name, $parameters = [], $secret = 'secret-key')
    {
        $url = self::route($name, $parameters);

        if ($url) {
            $signature = hash_hmac('sha256', $url, $secret);
            $url .= '?signature=' . $signature;
        }

        return $url;
    }

    public static function temporarySignedRoute($name, $parameters = [], $expiration, $secret = 'secret-key')
    {
        $parameters['expires'] = $expiration;
        $url = self::route($name, $parameters);

        if ($url) {
            $signature = hash_hmac('sha256', $url, $secret);
            $url .= '?expires=' . $expiration . '&signature=' . $signature;
        }

        return $url;
    }

    public static function validateSignature($url, $secret = 'secret-key')
    {
        $urlParts = parse_url($url);
        parse_str($urlParts['query'], $query);

        $expires = $query['expires'] ?? null;
        if ($expires && $expires < time()) {
            return false;
        }

        $originalUrl = $urlParts['scheme'] . '://' . $urlParts['host'] . $urlParts['path'];
        if (isset($urlParts['query'])) {
            unset($query['signature']);
            $originalUrl .= '?' . http_build_query($query);
        }

        $expectedSignature = hash_hmac('sha256', $originalUrl, $secret);
        return hash_equals($expectedSignature, $query['signature']);
    }
}

// Store the current URL before the script ends
// Url::storeCurrentUrl();
