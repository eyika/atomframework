<?php
namespace Eyika\Atom\Framework\Support\Auth\Guards;

use Eyika\Atom\Framework\Support\Auth\Contracts\AuthenticatableInterface;
use Eyika\Atom\Framework\Support\Auth\Drivers\DriverFactory;

abstract class Authenticator
{
    /**
     * The currently authenticated user.
     *
     * @var AuthenticatableInterface|null
     */
    protected $user;

    protected $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

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
     * @return AuthenticatableInterface|null
     */
    abstract public function attempt(array $credentials): ?AuthenticatableInterface;

    /**
     * Resolve the appropriate driver and validate credentials.
     *
     * @param array $credentials
     * @return AuthenticatableInterface|null
     */
    protected function validateCredentials(array $credentials): ?AuthenticatableInterface
    {
        $driver = $this->config['driver'];
        $handler = DriverFactory::getHandler($driver);

        return $handler->validateCredentials($credentials);
    }

    protected function getUserById($id): ?AuthenticatableInterface
    {
        $driver = $this->config['driver'];
        $handler = DriverFactory::getHandler($driver);

        return $handler->getUserById($id);
    }

    protected function getUserByColumn(string $columnName, $value): ?AuthenticatableInterface
    {
        $driver = $this->config['driver'];
        $handler = DriverFactory::getHandler($driver);

        return $handler->getUserByColumn($columnName, $value);
    }
}
