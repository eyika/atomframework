<?php
namespace Eyika\Atom\Framework\Support\Auth\Guards;

use Eyika\Atom\Framework\Exceptions\NotImplementedException;
use Eyika\Atom\Framework\Support\Auth\Contracts\AuthenticatableInterface;
use Eyika\Atom\Framework\Support\Auth\User;
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
        // Regenerate the session id on privilege change to prevent session fixation
        // (a pre-seeded id must not survive authentication).
        Session::regenerate();
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
            Response::setCookie('auth_remember', '', time() - 3600, "/", '', true, true);
        }

        Session::forget('user_id');
        Session::destroy();
    }

    public function remember(AuthenticatableInterface $user): void
    {
        // Encrypt-then-MAC the payload so the cookie can't be forged. Previously this
        // stored plaintext {"id":N}, so setting auth_remember={"id":1} = takeover.
        // HttpOnly + Secure; read back via recall().
        $token = encrypt(json_encode(['id' => $user->id, 'v' => 1]));
        Response::setCookie('auth_remember', $token, time() + (86400 * 30), "/", '', true, true);
    }

    /**
     * Resolve the user from a valid remember-me cookie (decrypt + verify), or null
     * if the cookie is absent, tampered, or maps to no user. Callers wire this into
     * their auto-login flow; a forged/plaintext cookie fails the MAC and returns null.
     */
    public function recall(): ?AuthenticatableInterface
    {
        $cookie = Request::cookie('auth_remember');
        $token = is_object($cookie) && method_exists($cookie, 'getValue') ? $cookie->getValue() : $cookie;
        if (empty($token)) {
            return null;
        }

        try {
            $data = json_decode(decrypt($token), true);
        } catch (\Throwable $e) {
            return null; // bad MAC / tampered / not encrypted by us
        }

        if (!is_array($data) || empty($data['id'])) {
            return null;
        }

        return $this->getUserById($data['id']);
    }

    public function refreshJwt(): ?string
    {
        throw new NotImplementedException('Session guard does not implement the refresh token method');
    }

    public function isValid(?string $token): bool
    {
        throw new NotImplementedException('Session guard does not implement the isValid method');
    }

    public function generateJwt(User $user, ?string $sid = null, bool $is_impersonating = false, ?int $impersonator_id = null, ?int $ttl = null): object
    {
        throw new NotImplementedException('Session guard does not implement the generateToken method');
    }
}
