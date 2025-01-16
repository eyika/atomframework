<?php

namespace Eyika\Atom\Framework\Support\Auth\Guards;

use Eyika\Atom\Framework\Support\Auth\Contracts\AuthenticatableInterface;
use Eyika\Atom\Framework\Support\Database\DB;

abstract class Authenticator
{
    /**
     * The data source for filling up the user object could be model, table
     * or any other defined and implemented by you
     */
    protected const MODEL_SOURCE = 'auth.providers.users.model';

    /**
     * The currently authenticated user.
     *
     * @var AuthenticatableInterface|null
     */
    protected $user;

    /**
     * Get the currently authenticated user.
     *
     * @return AuthenticatableInterface|null
     */
    public function user(): ?AuthenticatableInterface
    {
        return $this->user;
    }

    /**
     * Determine if the current user is authenticated.
     *
     * @return bool
     */
    public function check(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Log out the currently authenticated user.
     *
     * @return void
     */
    public function logout(): void
    {
        $this->user = null;
    }

    /**
     * Attempt to authenticate a user using the given credentials.
     *
     * @param array $credentials
     * @return AuthenticatableInterface
     */
    abstract public function attempt(array $credentials): ?AuthenticatableInterface;

    /**
     * Validate the provided credentials against the database.
     *
     * @param array $credentials
     * @return AuthenticatableInterface|null
     */
    protected function validateCredentials(array $credentials): ?AuthenticatableInterface
    {
        $user = DB::table('users')->where('email', $credentials['email'])->first();

        if ($user && password_verify($credentials['password'], $user['password'])) {
            return $this->toAuthenticatable($user);
        }

        return null;
    }

    /**
     * Transform the raw user data into an AuthenticatableInterface instance.
     *
     * @param array $user
     * @return AuthenticatableInterface
     */
    protected function toAuthenticatable(array $user): AuthenticatableInterface
    {
        $class = config(self::MODEL_SOURCE);
        return new $class($user);
    }
}
