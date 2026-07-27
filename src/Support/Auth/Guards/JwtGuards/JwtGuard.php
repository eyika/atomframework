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
    private $iss;
    private $aud;

    public function __construct(array $config, string $guard)
    {
        $this->iss = env('JWT_ISS');
        $this->aud = env('JWT_AUD');

        // Sign with app.key. (JWT_KEY was previously read but silently ignored —
        // encode() only takes the payload — so every token has always been signed
        // with app.key; switching keys now would invalidate all live tokens.)
        // Fail CLOSED: a JWT guard with no signing key can't verify anything.
        $signingKey = config('app.key');
        if (empty($signingKey)) {
            throw new \RuntimeException('JWT signing key (app.key) is not configured.');
        }

        $this->encoder = new JwtEncoder($signingKey);
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

        $isImpersonating = $payload->data->is_impersonating ?? false;
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

        // firebase/php-jwt validates exp/nbf/iat, but NOT issuer/audience — verify
        // them here when configured so a token minted for another service (same
        // key) is rejected.
        if (!$this->claimsValid($payload)) {
            return null;
        }

        return $payload;
    }

    /**
     * Verify issuer/audience claims when they are configured (JWT_ISS/JWT_AUD).
     * If neither is set, the check is skipped (backward compatible).
     */
    private function claimsValid(object $payload): bool
    {
        if (!empty($this->iss) && ($payload->iss ?? null) !== $this->iss) {
            return false;
        }
        if (!empty($this->aud) && ($payload->aud ?? null) !== $this->aud) {
            return false;
        }
        return true;
    }

    protected static function extractToken(): ?string
    {
        // Read the Authorization header from the bound request (WRK-11), falling back
        // to $_SERVER when no app/request is bound (CLI / unit context).
        $app = function_exists('app') ? app() : null;
        $auth_header = ($app && $app->bound('request'))
            ? app('request')->server('HTTP_AUTHORIZATION')
            : ($_SERVER['HTTP_AUTHORIZATION'] ?? null);
        if (empty($auth_header)) {
            return null;
        }

        // Do NOT run sanitize_data() over a credential — HTML-escaping/strip_tags a
        // bearer token is needless and can corrupt it. Extract the raw token.
        if (preg_match(self::HEADER_VALUE_PATTERN, $auth_header, $matches)) {
            return trim($matches[1]);
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

        $token = $this->encoder->encode($payload);
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
