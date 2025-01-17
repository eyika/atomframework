<?php
namespace Eyika\Atom\Framework\Support\Auth\Drivers;

use Eyika\Atom\Framework\Support\Auth\Contracts\AuthenticatableInterface;

class EloquentDriver implements DriverInterface
{
    public function validateCredentials(array $credentials): ?AuthenticatableInterface
    {
        $modelClass = config('auth.providers.users.model');
        $user = $modelClass::getBuilder()->where('email', $credentials['email'])->first(false);

        if ($user && password_verify($credentials['password'], $user->password)) {
            return $user;
        }

        return null;
    }

    public function getUserById($userId): ?AuthenticatableInterface
    {
        $modelClass = config('auth.providers.users.model');
        if (!$user = $modelClass::getBuilder()->where('id', $userId)->first(false)) {
            return null;
        }
        return $user;
    }

    public function getUserByColumn(string $columnName, $value): ?AuthenticatableInterface
    {
        $modelClass = config('auth.providers.users.model');
        if (!$user = $modelClass::getBuilder()->where($columnName, $value)->first(false)) {
            return null;
        }
        return $user;
    }
}
