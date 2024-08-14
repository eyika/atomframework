<?php

namespace Basttyy\FxDataServer\libs;

class Url
{
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
        // session_start();
        $_SESSION['previous_url'] = self::current();
    }

    /**
     * Retrieve the previous URL from the session.
     *
     * @return string|null
     */
    public static function previous()
    {
        // session_start();
        return isset($_SESSION['previous_url']) ? $_SESSION['previous_url'] : null;
    }
}

// Store the current URL before the script ends
// Url::storeCurrentUrl();
