<?php

namespace Eyika\Atom\Framework\Support\Auth;

use Eyika\Atom\Framework\Support\Arr;
use App\Models\Role;
use Eyika\Atom\Framework\Support\Auth\Jwt\JwtEncoder;

use function PHPSTORM_META\type;

abstract class Authenticator
{
    private static $user;
    protected static $type = 'jwt';

    const JWT = 'jwt';
    const SESSION = 'session';

    /**
     * Verify the user role against an (array|string) of role(s)
     * 
     * @param User $user
     * @param array|string $_role
     * @param bool $return_bool
     * 
     * @return bool
     */
    public static function verifyRole($user, $_role, $return_bool = true)
    {
        if (static::$type == static::JWT)
            new static(new JwtEncoder(env('APP_KEY')), $user);
        else
            new static($user);

        $_role = Arr::wrap($_role);

        if (!$role = Role::getBuilder()->orderBy()->findBy('id', $user->role_id)) {
            return false;
        }
        if (Arr::exists($_role, $role->name)) {
            return true;
        }
        return false;
    }

    /**
     * validate function
     *
     * @return bool|User
     */
    public static function validate()
    {
        return self::$user;
    }

    /**
     * validate function for firebase
     *
     * @return bool|User
     */
    public static function validateSocial()
    {
        return self::$user;
    }

    protected static function extractToken(): ?string
    {
        return null;
    }

    /**
     * uses firebase token to authenticate and generate a user's token
     *
     * @param User $user
     * @param string $password_or_token
     * @return string|bool
     */
    public static function authenticate(User $user, string $password_or_token = "")
    {
        return false;
    }
}
