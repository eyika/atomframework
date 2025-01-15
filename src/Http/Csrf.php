<?php

namespace Eyika\Atom\Framework\Http;

use Eyika\Atom\Framework\Support\Facade\Request;
use Eyika\Atom\Framework\Support\Facade\Session;

class Csrf
{
    private static string $phpTag = '<?php '; // hello hello hello.
    private static string $delimeter = '-|-';

    /**
     * Returns the current token. if there is not a token then it generates a new one.
     * It could require an open session.
     *
     * @param bool   $fullToken It returns a token with the current ip.
     * @param string $tokenId   [optional] Name of the token.
     *
     * @return string
     */
    public static function getCsrfToken($fullToken = false, $tokenId = 'csrf_token'): string
    {
        if (Session::has($tokenId)) {
            $csrf = Session::get($tokenId);
            return strstr($csrf, static::$delimeter, false) ?: $csrf;
        }
        $csrf = static::regenerateToken($tokenId);

        Session::set($tokenId, $fullToken ? $csrf . static::$delimeter . Request::clientIp() : $csrf);
        return $csrf;
    }

    /**
     * Regenerates the csrf token and stores in the session.
     * It requires an open session.
     * 
     * @return string
     */
    public static function regenerateToken(): string
    {
        try {
            return \bin2hex(\random_bytes(10));
        } catch (\Throwable $e) {
            return '123456789012345678901234567890'; // unable to generates a random token.
        }
    }

    // public static function setCsrf()
    // {
    //     return '<input type="hidden" name="csrf_token" value="' . static::getCsrf() . '">';
    // }

    public static function setCsrfToken($expression = 'csrf_token'): string
    {
        $tag = static::$phpTag;
        $csrf_token = static::getCsrfToken();
        return "<input type='hidden' name='{$tag} echo '$expression'; ?>' value='{$tag}echo '$csrf_token'; " . "?>'/>";
    }

    /**
     * Validates if the csrf token is valid or not.<br>
     * It requires an open session.
     *
     * @param bool   $alwaysRegenerate [optional] Default is false.<br>
     *                                 If **true** then it will generate a new token regardless
     *                                 of the method.<br>
     *                                 If **false**, then it will generate only if the method is POST.<br>
     *                                 Note: You must not use true if you want to use csrf with AJAX.
     *
     * @param string $tokenId          [optional] Name of the token.
     *
     * @return bool It returns true if the token is valid, or it is generated. Otherwise, false.
     */
    public static function csrfIsValid($alwaysRegenerate = false, $tokenId = '_token'): bool
    {
        $session_token = Session::get($tokenId, '');

        if (Request::isMethod('POST') && $alwaysRegenerate === false) {
            $csrf_token = Request::headers('X-CSRF-TOKEN') ?? Request::input($tokenId);
            $csrf_token = $token ?? Request::query($tokenId);
            $isFullToken = str_contains($session_token, static::$delimeter);
            $csrf_token = $isFullToken ? $csrf_token . static::$delimeter . Request::clientIp() : $csrf_token;

            return hash_equals($csrf_token, $session_token);
        }
        if ($session_token == '' || $alwaysRegenerate) {
            // if no token then we generate a new one
            static::regenerateToken($tokenId);
        }
        return true;
    }
}
