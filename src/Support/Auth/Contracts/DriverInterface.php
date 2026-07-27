<?php
namespace Eyika\Atom\Framework\Support\Auth\Contracts;

use Eyika\Atom\Framework\Support\Auth\Contracts\AuthenticatableInterface;

interface DriverInterface
{
    public function validateCredentials(array $credentials): ?AuthenticatableInterface;

    public function getUserById($id): ?AuthenticatableInterface;

    public function getUserByColumn(string $columnName, $value): ?AuthenticatableInterface;
}
