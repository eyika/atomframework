<?php

namespace Eyika\Atom\Framework\Support\Auth;

use Eyika\Atom\Framework\Support\Auth\Contracts\AuthenticatableInterface;
use Eyika\Atom\Framework\Support\Auth\Guards\Authenticator;

final class Auth
{
    protected static $user;
    protected static $guardName;
    protected static array $guards = [];

    /**
     * Initialize the Auth class with available guards.
     */
    public static function init(array $config): void
    {
        static::$guards = $config['guards'] ?? [];
        static::$guardName = $config['defaults']['guard'] ?? 'web';
    }

    /**
     * Get the current guard instance or a specific guard.
     */
    public static function guard(string $name = null): Authenticator
    {
        $name = $name ?? static::$guardName;

        if (!isset(static::$guards[$name])) {
            throw new \InvalidArgumentException("Guard [$name] is not defined.");
        }

        $guardConfig = static::$guards[$name];
        $driverClass = static::resolveDriverClass($guardConfig['driver'], $guardConfig['driver_classes']);

        return new $driverClass($guardConfig);
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
        return static::$user;
    }

    /**
     * Check if the user is authenticated.
     */
    public static function check(): bool
    {
        return static::$user !== null;
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
}
