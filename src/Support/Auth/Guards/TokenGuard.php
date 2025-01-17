<?php
namespace Eyika\Atom\Framework\Support\Auth\Guards;

use Eyika\Atom\Framework\Exceptions\NotImplementedException;
use Eyika\Atom\Framework\Support\Auth\Contracts\AuthenticatableInterface;
use Eyika\Atom\Framework\Support\Database\DB;
use Eyika\Atom\Framework\Support\Facade\Request as RequestFacade;

class TokenGuard extends Authenticator
{
    protected $config;

    protected $tokenName;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->tokenName = config('auth.token_name');
    }

    public function attempt(array $credentials): ?AuthenticatableInterface
    {
        if (!$user = $this->getUserByColumn($this->tokenName, $credentials['token'])) {
            return null;
        }
        return $user;
    }

    public function check(): bool
    {
        $token = RequestFacade::header('Authorization');
        return $this->user($token) !== null;
    }

    public function user(?string $token = null): ?AuthenticatableInterface
    {
        if (!$token) {
            $token = RequestFacade::header('Authorization');
        }
        if (!$user = $this->getUserByColumn($this->tokenName, $token)) {
            return null;
        }
        return $user;
    }

    public function logout(): void
    {
        // Token-based guards typically don't maintain session state.
    }

    public function remember(AuthenticatableInterface $user): void
    {
        throw new NotImplementedException('Remember functionality not applicable to token-based guards');
    }
}
