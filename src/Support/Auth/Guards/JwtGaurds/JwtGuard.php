<?php

namespace Eyika\Atom\Framework\Support\Auth\Guards\JwtGuards;

use Eyika\Atom\Framework\Exceptions\NotImplementedException;
use Eyika\Atom\Framework\Support\Auth\Contracts\AuthenticatableInterface;
use Eyika\Atom\Framework\Support\Auth\Guards\Authenticator;
use Eyika\Atom\Framework\Support\Auth\User;
use Eyika\Atom\Framework\Support\Database\DB;

final class JwtGuard extends Authenticator
{
    private const HEADER_VALUE_PATTERN = "/Bearer\s+(.*)$/i";

    private $encoder;

    // variables used for jwt
    private $key;
    private $iss;
    private $aud;

    public function __construct(JwtEncoder $encoder, AuthenticatableInterface $user)
    {
        $this->key = env('JWT_KEY');
        $this->iss = env('JWT_ISS');
        $this->aud = env('JWT_AUD');
        $this->encoder = $encoder ?? new JwtEncoder(config('app.key'));
        $this->user = $user;
    }

    public function attempt(array $credentials): ?AuthenticatableInterface
    {
        $user = DB::table('users')->where('email', $credentials['email'])->first();

        if ($user && password_verify($credentials['password'], $user['password'])) {
            $user = $this->toAuthenticatable($user);
            $user->{config('auth.token_name')} = $this->generateJwt($user);

            return $user;
        }

        return null;
    }

    public function check(): bool
    {
        return $this->validate();
    }

    /**
     * validate function
     *
     * @return bool|User
     */
    private function validate()
    {
        new static(new JwtEncoder(env('APP_KEY')), new User);
        $jwt = $this->extractToken();
        if (empty($jwt)) {
            return false;
        }

        if (is_null($payload = self::$encoder->decode($jwt))) {
            return false;
        }
        
        if (!self::$user->find($payload->data->id, false)) {
            return false;
        }

        return self::$user;
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
    public static function generateJwt(User $user)
    {
        $issued_at = time();
        $expiration_time = $issued_at + (60 * 60);      //valid for one hour
        $not_before = $issued_at - 5;

        $token = self::$encoder->encode([
            "iss" => self::$iss,
            "aud" => self::$aud,
            "iat" => $issued_at,
            "nbf" => $not_before,
            "exp" => $expiration_time,
            'data' => [
                "id" => $user->id,
                "email" => $user->email,
            ]
        ], self::$key);
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
