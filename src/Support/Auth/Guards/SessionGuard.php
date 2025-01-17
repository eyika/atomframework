<?php
namespace Eyika\Atom\Framework\Support\Auth\Guards;

use Eyika\Atom\Framework\Exceptions\NotImplementedException;
use Eyika\Atom\Framework\Support\Auth\Contracts\AuthenticatableInterface;
use Eyika\Atom\Framework\Support\Database\DB;
use Eyika\Atom\Framework\Support\Facade\Request;
use Eyika\Atom\Framework\Support\Facade\Response;
use Eyika\Atom\Framework\Support\Facade\Session;

class SessionGuard extends Authenticator
{
    public function __construct(array $config, string $guard)
    {
        $this->config = $config;
        $this->guard = $guard;
    }

    public function attempt(array $credentials): ?AuthenticatableInterface
    {
        if (!$user = $this->validateCredentials($credentials)) {
            return null;
        }
        Session::set('user_id', $user->id);
        return $user;
    }

    public function check(): bool
    {
        return Session::has('user_id');
    }

    public function user(): ?AuthenticatableInterface
    {
        if ($this->check()) {
            $userId = Session::get('user_id');
            if (!$user = $this->getUserById($userId)) {
                return null;
            }
            return $user;
        }
        return null;
    }

    public function logout(): void
    {
        $this->user = null;

        if (Request::cookie('auth_remember')) {
            Response::setCookie('auth_remember', '', time() - 3600, "/");
        }

        Session::forget('user_id');
        Session::destroy();
    }

    public function remember(AuthenticatableInterface $user): void
    {
        Response::setCookie('auth_remember', json_encode(['id' => $user->id]), time() + (86400 * 30), "/");
    }

    public function refreshJwt(): ?AuthenticatableInterface
    {
        throw new NotImplementedException('Session guard does not implement the refresh token method');
    }
}
