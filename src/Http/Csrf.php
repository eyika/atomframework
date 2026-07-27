<?php

namespace Eyika\Atom\Framework\Http;

use Eyika\Atom\Framework\Support\Facade\Request;
use Eyika\Atom\Framework\Support\Facade\Session;

class Csrf
{
    private static string $delimeter = '-|-';

    /** Session key under which the token is stored (single source of truth). */
    public const SESSION_KEY = 'csrf_token';

    /** Read-only verbs that don't mutate state and are exempt from verification. */
    private const READ_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    /**
     * Return the current token, generating + storing one if absent.
     * Requires an open session.
     */
    public static function getCsrfToken(bool $fullToken = false, string $tokenId = self::SESSION_KEY): string
    {
        if (Session::has($tokenId)) {
            $stored = Session::get($tokenId);
            // Return only the raw token part (strip any "-|-ip" binding suffix).
            return strstr($stored, static::$delimeter, true) ?: $stored;
        }

        $csrf = static::regenerateToken();
        Session::set($tokenId, $fullToken ? $csrf . static::$delimeter . Request::clientIp() : $csrf);

        return $csrf;
    }

    /**
     * Generate a fresh random token. Fails CLOSED — on RNG failure random_bytes()
     * throws and propagates rather than returning a predictable constant (the old
     * behaviour returned a fixed '123…' string, making tokens guessable).
     */
    public static function regenerateToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Render a hidden form field carrying the current token.
     */
    public static function field(string $tokenId = self::SESSION_KEY): string
    {
        $token = htmlspecialchars(static::getCsrfToken(false, $tokenId), ENT_QUOTES);
        return '<input type="hidden" name="_token" value="' . $token . '">';
    }

    /**
     * Back-compat alias for field() (previously emitted a broken literal PHP tag).
     */
    public static function setCsrfToken(string $tokenId = self::SESSION_KEY): string
    {
        return static::field($tokenId);
    }

    /**
     * Validate the request's CSRF token against the session.
     *
     * Read-method requests (GET/HEAD/OPTIONS) are always allowed; every
     * state-changing verb (POST/PUT/PATCH/DELETE/…) must present a matching token.
     * The token is accepted from the X-CSRF-TOKEN header, the `_token` field, the
     * configured session key field, or the `_token` query param. Comparison is
     * constant-time and fails CLOSED when no server-side token exists.
     */
    public static function csrfIsValid(string $tokenId = self::SESSION_KEY): bool
    {
        if (in_array(strtoupper(Request::method()), self::READ_METHODS, true)) {
            return true;
        }

        $sessionToken = Session::get($tokenId, '');
        if (!is_string($sessionToken) || $sessionToken === '') {
            return false; // nothing to compare against → reject
        }

        $provided = Request::headers('X-CSRF-TOKEN')
            ?? Request::input('_token')
            ?? Request::input($tokenId)
            ?? Request::query('_token');

        if (!is_string($provided) || $provided === '') {
            return false;
        }

        // If the stored token is IP-bound (token-|-ip), the client sends only the raw
        // token, so reconstruct the bound form before comparing.
        $candidate = str_contains($sessionToken, static::$delimeter)
            ? $provided . static::$delimeter . Request::clientIp()
            : $provided;

        return hash_equals($sessionToken, $candidate);
    }
}
