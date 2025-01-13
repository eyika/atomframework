<?php

namespace Eyika\Atom\Framework\Support\Auth;

use Eyika\Atom\Framework\Support\Auth\Contracts\AuthenticatableInterface;
use Eyika\Atom\Framework\Support\Database\DB;
use App\Models\User;
use Eyika\Atom\Framework\Support\Facade\Request as RequestFacade;

final class Auth
{
    protected static $user;
    protected static $guard;
    protected static Authenticator $authenticator;

        /**
     * Try to validate a user
     * 
     * @return bool|User
     */
    public static function tryToAuthenticate()
    {
        return static::$authenticator->validate();
    }

    /**
     * Verify a user's role using a(n) string/array of roles
     * 
     * @param Authenticatable|User $user
     * @param array|string $role
     * 
     * @return bool
     */
    public static function roleIs($user, $role)
    {
        return static::$authenticator->verifyRole($user, $role);
    }

    /**
     * Verify a user's role is not equal to a role using a(n) string/array of roles
     * 
     * @param Authenticatable|User $user
     * @param array|string $role
     * 
     * @return bool
     */
    public static function roleIsNot($user, $role)
    {
        return !static::$authenticator->verifyRole($user, $role);
    }

    public static function setUser($user): void
    {
        static::$user = $user;
    }

    public static function user(): AuthenticatableInterface
    {
        return isset(static::$user) ? static::$user : static::$user;
    }

    public static function check(): bool
    {
        return static::$user !== null;
    }

    public static function attempt(array $credentials, bool $remember = true): bool
    {
        $user = static::validateCredentials($credentials);

        if ($user && RequestFacade::wantsJson()) {
            static::setUser($user);
            if ($remember) {
                setcookie('auth_remember', json_encode($credentials), time() + (86400 * 30), "/");
            }
            return true;
        }

        return false;
    }

    protected static function validateCredentials(array $credentials)
    {
        $user = DB::table('users')->where('email', $credentials['email'])->first();

        if ($user && password_verify($credentials['password'], $user->password)) {
            return $user;
        }

        return null;
    }

    public static function logout(): void
    {
        static::$user = null;

        if (isset($_COOKIE['auth_remember'])) {
            setcookie('auth_remember', '', time() - 3600, "/");
        }

        session_destroy();
    }

    public static function guard(string $name = null)
    {

        return new static;
    }
}
