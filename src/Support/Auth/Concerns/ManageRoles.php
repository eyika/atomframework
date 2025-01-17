<?php

namespace Eyika\Atom\Framework\Support\Auth\Concerns;

use Eyika\Atom\Framework\Support\Arr;
use App\Models\Role;

use function PHPSTORM_META\type;

trait ManageRoles
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
     * Verify a user's role using a(n) string/array of roles
     * 
     * @param Authenticatable|User $user
     * @param array|string $role
     * 
     * @return bool
     */
    public static function roleIs($user, $role)
    {
        return static::verifyRole($user, $role);
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
        return !static::verifyRole($user, $role);
    }
}
