<?php
namespace Eyika\Atom\Framework\Support\Auth\Guards;

use Eyika\Atom\Framework\Support\Auth\Contracts\AuthenticatableInterface;
use Eyika\Atom\Framework\Support\Database\DB;
use Eyika\Atom\Framework\Support\Facade\Request;
use Eyika\Atom\Framework\Support\Facade\Response;
use Eyika\Atom\Framework\Support\Facade\Session;

class SessionGuard extends Authenticator
{
    protected $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function attempt(array $credentials): ?AuthenticatableInterface
    {
        $user = DB::table('users')->where('email', $credentials['email'])->first();

        if ($user && password_verify($credentials['password'], $user['password'])) {
            Session::set('user_id', $user->id);
            return $this->toAuthenticatable($user);
        }

        return null;
    }

    public function check(): bool
    {
        return Session::has('user_id');
    }

    public function user(): ?AuthenticatableInterface
    {
        if ($this->check()) {
            $userId = Session::get('user_id');
            return DB::table('users')->where('id', $userId)->first();
        }
        return null;
    }

    public function logout(): void
    {
        static::$user = null;

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
}
