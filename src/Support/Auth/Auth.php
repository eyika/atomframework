<?php

namespace Eyika\Atom\Framework\Support\Auth;

use Eyika\Atom\Framework\Support\Auth\Concerns\ManageRoles;
use Eyika\Atom\Framework\Support\Auth\Contracts\AuthenticatableInterface;
use Eyika\Atom\Framework\Support\Auth\Guards\Authenticator;

final class Auth
{
    use ManageRoles;

    protected static $user;
    protected static $guardName;
    protected static array $config;
    protected static string $jwt;

    /**
     * Initialize the Auth class with available guards.
     */
    public static function init(array|null $config = null): void
    {
        if (isset(static::$guardName) && isset(static::$guards)) {
            return;
        }
        static::$config = $config ?? config('auth', []);
        static::$guardName = $config['defaults']['guard'] ?? 'web';
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
    public static function user(): ?AuthenticatableInterface
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
    public static function attempt(array $credentials, bool $remember = false): bool
    {
        $guard = static::guard();
        $user = $guard->attempt($credentials);

        if ($user) {
            static::setUser($user);
            if ($remember && method_exists($guard, 'remember')) {
                $guard->remember($user);
            }
            return true;
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
     * Set the logged in user's jwt token for later retrieval
     * Usually this will be called internally by the JwtGuard
     */
    public static function setJwt(string $jwt)
    {
        static::$jwt = $jwt;
    }

    /**
     * Get the logged in user's jwt token
     */
    public static function getJwt()
    {
        return static::$jwt;
    }
}
