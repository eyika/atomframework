<?php
namespace Eyika\Atom\Framework\Support\Auth\Drivers;

use Eyika\Atom\Framework\Support\Auth\Contracts\AuthenticatableInterface;
use Eyika\Atom\Framework\Support\Auth\Contracts\DriverInterface;

class EloquentDriver implements DriverInterface
{
    protected string $provider;

    public function __construct(string $provider)
    {
        $this->provider = $provider;
    }

    /**
     * The model this provider authenticates against. Resolves the guard's provider model
     * (`auth.providers.<provider>.model`) so multiple guards/providers can each use their own
     * model; falls back to the legacy global `auth.user.model` for single-guard apps.
     */
    protected function modelClass(): string
    {
        return config("auth.providers.{$this->provider}.model") ?? config('auth.user.model');
    }

    public function validateCredentials(array $credentials): ?AuthenticatableInterface
    {
        $modelClass = $this->modelClass();
        $user = $modelClass::getBuilder()->where('email', $credentials['email'])->first(false);

        if ($user && password_verify($credentials['password'], $user->password)) {
            return $user;
        }

        return null;
    }

    public function getUserById($userId): ?AuthenticatableInterface
    {
        $modelClass = $this->modelClass();
        if (!$user = $modelClass::getBuilder()->where('id', $userId)->first(false)) {
            return null;
        }
        return $user;
    }

    public function getUserByColumn(string $columnName, $value): ?AuthenticatableInterface
    {
        $modelClass = $this->modelClass();
        if (!$user = $modelClass::getBuilder()->where($columnName, $value)->first(false)) {
            return null;
        }
        return $user;
    }
}
