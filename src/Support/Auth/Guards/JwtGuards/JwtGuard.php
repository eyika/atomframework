<?php

namespace Eyika\Atom\Framework\Support\Auth\Guards\JwtGuards;

use Eyika\Atom\Framework\Exceptions\NotImplementedException;
use Eyika\Atom\Framework\Support\Str;
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

    /**
     * Validate the user's credentials, Generate and set a jwt for the current user
     */
    public function attempt(array $credentials): ?AuthenticatableInterface
    {
        if (!$user = $this->validateCredentials($credentials)) {
            return null;
        }

        $jwt = $this->generateJwt($user);
        Auth::setJwt($jwt->token);
        Auth::setSid($jwt->sid);
        return $user;
    }

    /**
     * Check that the user has a valid token and set the current user
     */
    public function check(): bool
    {
        return (bool)$this->validate();
    }

    /**
     * Check that a user's token is valid
     */
    public function isValid(?string $token): bool
    {
        return $this->validate($token);
    }

    /**
     * Get the current user based on a token or the request headers
     */
    public function user(?string $token = null): ?AuthenticatableInterface
    {
        if (!$user_id = $this->validate()) {
            return null;
        }
        
        if (!$user = $this->getUserById($user_id)) {
            return null;
        }

        return $user;
    }

    /**
     * Refresh the current user's jwt token based on the request header
     */
    public function refreshJwt(): ?string
    {
        if (!$user = $this->user()) {
            return null;
        }
        if (!$sid = $this->getSid()) {
            return null;
        }
        Auth::setSid($sid);
        $jwt = $this->generateJwt($user, $sid);
        return $jwt->token;
    }

    public function remember(AuthenticatableInterface $user): void
    {
        throw new NotImplementedException('Jwt guard does not implement the remember method');
    }

    /**
     * validate function
     *
     * @return bool|int
     */
    protected function validate(?string $jwt = null): ?int
    {
        if (is_null($payload = $this->getPayload())) {
            return null;
        }

        $isImpersonating = $payload->data->is_impersonating;
        $impersonatorId = $payload->data->impersonator_id ?? null;

        Auth::setImpersonation($isImpersonating, $impersonatorId);

        Auth::setSid($payload->sid ?? null);

        return $payload->data->id;
    }

    /**
     * Get the current session id
     *
     * @return null|string
     */
    protected function getSid(): ?string
    {
        if (is_null($payload = $this->getPayload())) {
            return null;
        }

        return $payload->sid;
    }

    /**
     * Get the jwt's payload data
     *
     * @return null|object
     */
    protected function getPayload(): ?object
    {
        $jwt = $this->extractToken();
        if (empty($jwt)) {
            return null;
        }

        if (is_null($payload = $this->encoder->decode($jwt))) {
            return null;
        }

        return $payload;
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
     */
    public function generateJwt(User $user, ?string $sid = null, bool $is_impersonating = false, ?int $impersonator_id = null, ?int $ttl = null): object
    {
        $issued_at = time();
        $expiration_time = $issued_at + ($ttl ?? config('auth.jwt_timeout', 60 * 60));      // fallback
        $not_before = $issued_at - 5;
        if (!$sid)
            $sid = Str::uuid()->toString();

        $payload = [
            "iss" => $this->iss,
            "aud" => $this->aud,
            "iat" => $issued_at,
            "nbf" => $not_before,
            "exp" => $expiration_time,
            "sid" => $sid,
            'data' => [
                "id" => $user->id,
                "email" => $user->email,
                "is_impersonating" => $is_impersonating,
                "impersonator_id" => $impersonator_id
            ]
        ];

        $token = $this->encoder->encode($payload, $this->key);
        return (object)[
            "token" => $token,
            "sid" => $sid
        ];
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
