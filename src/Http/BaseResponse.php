<?php

namespace Eyika\Atom\Framework\Http;

use Cookie;

class BaseResponse
{
    protected static $headers = [];

    // Method to set a cookie header
    public static function setCookie(Cookie $cookie)
    {
        static::$headers[] = ['Set-Cookie' => [$cookie, 0]];
    }

    // Method to set a header
    public static function setHeader(string $key, string $content, int $code = 0)
    {
        static::$headers[] = [$key => [$content, $code]];
    }

    public static function cookies()
    {
        $cookies = [];

        foreach (static::$headers as $header) {
            foreach ($header as $key => $value) {
                if (str_contains($key, 'Set-Cookie')) {
                    $cookies[] = [$key => $value];
                }
            }
        }
        return $cookies;
    }

    // Method to send the response headers and content
    public static function send()
    {
        static::sendHeaders();
        // Additional logic to send content (omitted for brevity)
    }

    protected static function sendHeaders()
    {
        foreach (static::$headers as $header) {
            foreach ($header as $key => $value) {
                $val = str_contains($key, 'Set-Cookie') ? (string) $value[0] : $value[0];
                header("{$key}: {$val}", $value[1]);
            }
        }
    }
}
