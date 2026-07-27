<?php

namespace Eyika\Atom\Framework\Support\Auth;

use Eyika\Atom\Framework\Support\Auth\Concerns\ManageRoles;
use Eyika\Atom\Framework\Support\Auth\Contracts\AuthenticatableInterface;
use Eyika\Atom\Framework\Support\Auth\Guards\Authenticator;
use Eyika\Atom\Framework\Support\Facade\Request;

final class Auth
{
    use ManageRoles;

    protected static $user;
    protected static $guardName;
    protected static array $config;
    protected static string $jwt;
    protected static string $sid;
    protected static ?int $impersonatorId = null;
    protected static bool $isImpersonating = false;

    /** @property Authenticator[] */
    protected static $guards = [];

    /**
     * Initialize the Auth class with available guards.
     */
    public static function init(array|null $config = null): void
    {
        if (isset(static::$guardName) && isset(static::$guards)) {
            return;
        }
        static::$config = $config ?? config('auth', []);
        static::$guardName = Request::wantsJson() ? 'api' : ($config['defaults']['guard'] ?? 'web');
        static::$jwt = '';
    }

    public static function getDefaultGuard()
    {
        static::init();
        return static::$guardName;
    }

    /**
     * Get the current guard instance or a specific guard.
     */
    public static function guard(string|null $name = null): Authenticator
    {
        if ($name && isset(static::$guards[$name])) {
            return static::$guards[$name];
        }
        static::init();
        $name = $name ?? static::$guardName;

        if (!isset(static::$config['guards'][$name])) {
            throw new \InvalidArgumentException("Guard [$name] is not defined.");
        }

        $guardConfig = static::$config['guards'][$name];
        $driverClass = static::resolveDriverClass($guardConfig['driver'], static::$config['driver_classes']);

        return new $driverClass(static::$config, $name);
    }

    /**
     * Resolve the driver class based on the driver name.
     */
    protected static function resolveDriverClass(string $driver, array $drivers): string
    {
        if (!isset($drivers[$driver])) {
            throw new \InvalidArgumentException("Driver [$driver] is not supported.");
        }

        return $drivers[$driver];
    }

    /**
     * Set the authenticated user.
     */
    public static function setUser(AuthenticatableInterface $user): void
    {
        static::$user = $user;
    }

    /**
     * Get the authenticated user.
     */
    public static function user(): null|AuthenticatableInterface|User
    {
        if (!isset(static::$user)) {
            $guard = static::guard();
            static::$user = $guard->user();
        }

        return static::$user;
    }

    /**
     * Check if the user is authenticated.
     */
    public static function check(): bool
    {
        $guard = static::guard();
        return $guard->check();
    }

    /**
     * Attempt to authenticate a user using the current guard.
     */
    public static function attempt(array $credentials, bool $remember = false): AuthenticatableInterface|false
    {
        $guard = static::guard();
        $user = $guard->attempt($credentials);

        if ($user) {
            static::setUser($user);
            if ($remember && method_exists($guard, 'remember')) {
                $guard->remember($user);
            }
            return $user;
        }

        return false;
    }

    /**
     * Log out the user using the current guard.
     */
    public static function logout(): void
    {
        $guard = static::guard();
        $guard->logout();
        static::$user = null;
    }

    /**
     * Validate the user's token or session.
     */
    public static function isValid(?string $token): bool
    {
        $guard = static::guard();

        return $guard->isValid($token);
    }

    /**
     * Set the logged in user's jwt token for later retrieval
     * Usually this will be called internally by the JwtGuard
     */
    public static function setJwt(string $jwt)
    {
        static::$jwt = $jwt;
    }

    /**
     * Set the logged in user's session_id for later retrieval
     * Usually this will be called internally by the JwtGuard
     */
    public static function setSid(string $sid)
    {
        static::$sid = $sid;
    }

    /**
     * Get the logged in user's jwt token
     */
    public static function getJwt()
    {
        return static::$jwt;
    }

    /**
     * Get the logged in user's sid token
     */
    public static function getSid()
    {
        return static::$sid;
    }

    /**
     * Reset ALL per-request identity state (WRK-03). Under a persistent worker the
     * static resolved user/jwt/sid/impersonation would otherwise leak into the next
     * request — a cross-user identity leak. The worker calls this between requests.
     * Configuration ($config) is intentionally left intact; the guard name is cleared
     * so it is re-derived from the next request.
     */
    public static function flush(): void
    {
        static::$user = null;
        static::$jwt = '';
        static::$sid = '';
        // Untyped → null makes isset() false, so init() re-derives the guard from the
        // next request (a static property cannot be unset()).
        static::$guardName = null;
        static::$isImpersonating = false;
        static::$impersonatorId = null;
        static::$guards = [];
    }

    public static function setImpersonation(bool $isImpersonating, ?int $impersonatorId = null): void
    {
        static::$isImpersonating = $isImpersonating;
        static::$impersonatorId = $impersonatorId;
    }

    public static function isImpersonating(): bool
    {
        return (bool) static::$isImpersonating;
    }

    public static function getImpersonatorId(): ?int
    {
        return static::$impersonatorId;
    }

    /**
     * Attempt to refresh the current jwt_token
     * This method only applies to jwt related guards hence only requests that has such guard can use this method
     */
    public static function refreshJwt(): ?string
    {
        $guard = static::guard();
        static::$jwt = $guard->refreshJwt();

        return static::$jwt;
    }
}
