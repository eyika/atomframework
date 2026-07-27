<?php
namespace Eyika\Atom\Framework\Support\Auth\Drivers;

use Eyika\Atom\Framework\Support\Auth\Contracts\AuthenticatableInterface;
use Eyika\Atom\Framework\Support\Auth\Contracts\DriverInterface;
use Eyika\Atom\Framework\Support\Database\DB;

class DatabaseDriver implements DriverInterface
{
    protected string $provider;

    public function __construct(string $provider)
    {
        $this->provider = $provider;
    }

    public function validateCredentials(array $credentials): ?AuthenticatableInterface
    {
        $table = config("auth.providers.$this->provider.table", 'users');
        $user = DB::table($table)->where('email', $credentials['email'])->first();

        if ($user && password_verify($credentials['password'], $user['password'])) {
            return $this->toAuthenticatable($user);
        }

        return null;
    }

    public function getUserById($userId): ?AuthenticatableInterface
    {
        $table = config("auth.providers.$this->provider.table", 'users');
        if (!$user = DB::table($table)->where('id', $userId)->first()) {
            return null;
        }
        return $this->toAuthenticatable($user);
    }

    public function getUserByColumn(string $columnName, $value): ?AuthenticatableInterface
    {
        $table = config("auth.providers.$this->provider.table", 'users');
        if (!$user = DB::table($table)->where($columnName, $value)->first()) {
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
