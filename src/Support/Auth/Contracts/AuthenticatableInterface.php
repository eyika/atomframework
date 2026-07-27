<?php

namespace Eyika\Atom\Framework\Support\Auth\Contracts;

use Eyika\Atom\Framework\Support\Database\Contracts\UserModelInterface;

interface AuthenticatableInterface extends UserModelInterface
{
    /**
     * Get the name of the unique identifier for the user.
     *
     * @return string
     */
    public function getAuthIdentifierName();

    /**
     * Get the unique identifier for the user.
     *
     * @return mixed
     */
    public function getAuthIdentifier();

    /**
     * Get the password for the user.
     *
     * @return string
     */
    public function getAuthPassword();

    /**
     * Get the "remember me" token value.
     *
     * @return string|null
     */
    public function getRememberToken();

    /**
     * Set the "remember me" token value.
     *
     * @param  string  $value
     * @return void
     */
    public function setRememberToken($value);

    /**
     * Get the name of the "remember me" token.
     *
     * @return string
     */
    public function getRememberTokenName();
}
