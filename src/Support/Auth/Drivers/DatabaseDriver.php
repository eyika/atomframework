<?php
namespace Eyika\Atom\Framework\Support\Auth\Drivers;

use Eyika\Atom\Framework\Support\Auth\Contracts\AuthenticatableInterface;
use Eyika\Atom\Framework\Support\Database\DB;

class DatabaseDriver implements DriverInterface
{
    public function validateCredentials(array $credentials): ?AuthenticatableInterface
    {
        $table = config('auth.providers.users.table', 'users');
        $user = DB::table($table)->where('email', $credentials['email'])->first(false);

        if ($user && password_verify($credentials['password'], $user['password'])) {
            return $this->toAuthenticatable($user);
        }

        return null;
    }

    public function getUserById($userId): ?AuthenticatableInterface
    {
        if (!$user = DB::table('users')->where('id', $userId)->first(false)) {
            return null;
        }
        return $this->toAuthenticatable($user);
    }

    public function getUserByColumn(string $columnName, $value): ?AuthenticatableInterface
    {
        if (!$user = DB::table('users')->where($columnName, $value)->first(false)) {
            return null;
        }
        return $this->toAuthenticatable($user);
    }

    protected function toAuthenticatable(array $user): AuthenticatableInterface
    {
        $class = config('auth.user.model');
        return new $class($user);
    }
}
