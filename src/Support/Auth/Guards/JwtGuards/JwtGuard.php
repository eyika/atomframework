<?php

namespace Eyika\Atom\Framework\Support\Auth\Guards\JwtGuards;

use Eyika\Atom\Framework\Exceptions\NotImplementedException;
use Eyika\Atom\Framework\Support\Auth\Auth;
use Eyika\Atom\Framework\Support\Auth\Contracts\AuthenticatableInterface;
use Eyika\Atom\Framework\Support\Auth\Guards\Authenticator;
use Eyika\Atom\Framework\Support\Auth\User;

class JwtGuard extends Authenticator
{
    private const HEADER_VALUE_PATTERN = "/Bearer\s+(.*)$/i";

    private $encoder;

    // variables used for jwt
    private $key;
    private $iss;
    private $aud;

    public function __construct(array $config, string $guard)
    {
        $this->key = env('JWT_KEY');
        $this->iss = env('JWT_ISS');
        $this->aud = env('JWT_AUD');
        $this->encoder = new JwtEncoder(config('app.key'));
        $this->config = $config;
        $this->guard = $guard;
    }

    public function attempt(array $credentials): ?AuthenticatableInterface
    {
        if (!$user = $this->validateCredentials($credentials)) {
            return null;
        }
        Auth::setJwt($this->generateJwt($user));
        return $user;
    }

    public function check(): bool
    {
        return (bool)$this->validate();
    }

    public function user(?string $token = null): ?AuthenticatableInterface
    {
        // if ($this->user) {
        //     return $this->user;
        // }
        if (!$user_id = $this->validate()) {
            return null;
        }
        
        if (!$user = $this->getUserById($user_id)) {
            return null;
        }

        return $user;
    }

    public function refreshJwt(): ?AuthenticatableInterface
    {
        if (!$user = $this->user()) {
            return null;
        }
        $jwt = $this->generateJwt($user);
        return $jwt;
    }

    /**
     * validate function
     *
     * @return null|int
     */
    protected function validate(): ?int
    {
        $jwt = $this->extractToken();
        if (empty($jwt)) {
            return null;
        }

        if (is_null($payload = $this->encoder->decode($jwt))) {
            return null;
        }

        return $payload->data->id;
    }

    protected static function extractToken(): ?string
    {
        if (!isset($_SERVER['HTTP_AUTHORIZATION']))
            return null;
        $auth_header = $_SERVER['HTTP_AUTHORIZATION'];
        if (empty($auth_header)) {
            return null;
        }

        $auth_token = sanitize_data($auth_header);
        if (empty($auth_token)) {
            return null;
        }

        if (preg_match(self::HEADER_VALUE_PATTERN, $auth_token, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * generates a time base user's jwt token
     *
     * @param User $user
     * @return string|bool
     */
    public function generateJwt(User $user)
    {
        $issued_at = time();
        $expiration_time = $issued_at + config('auth.jwt_timeout', 60 * 60);      //valid for one hour by default
        $not_before = $issued_at - 5;

        $token = $this->encoder->encode([
            "iss" => $this->iss,
            "aud" => $this->aud,
            "iat" => $issued_at,
            "nbf" => $not_before,
            "exp" => $expiration_time,
            'data' => [
                "id" => $user->id,
                "email" => $user->email,
            ]
        ], $this->key);
        return $token;
    }

    /**
     * validate function for social accounts
     *
     * @return bool|User
     */
    public function validateSocial()
    {
        throw new NotImplementedException('this method is not yet implemented');


        // if (str_contains($jwt, "social_login:")) {
        //     $providers = ['facebook']; //, 'twitter', 'google'];
        //     $hybridauth = new Hybridauth("{$_SERVER['DOCUMENT_ROOT']}/../hybridauth_config.php");  //, null, new DbStorage('SOCIALAUTH::STORAGE'));

        //     foreach ($providers as $provider) {
        //         if ($hybridauth->isConnectedWith($provider)) {
        //             $adapter = $hybridauth->getAdapter($provider);
        //             break;
        //         }
        //         $adapter = null;
        //     }
        //     if ($adapter instanceof AdapterInterface) {
        //         if (!self::$user->find((int)base64_decode(str_replace('social_login:', '', $jwt), false))) {
        //             return false;
        //         }
        //         $user_profile = $adapter->getUserProfile();
        //         if (self::$user->uuid !== $user_profile->identifier && self::$user->email !== $user_profile->email) {
        //             return false;
        //         }
        //         return self::$user;
        //     }
        //     return false;
        // }

        // $user = self::$user->fill((array)$payload->data);
        // if (!self::authenticate($user, $social_token)) {
        //     return false;
        // }
        // return $user;
    }
}
