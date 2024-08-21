<?php

namespace Eyika\Atom\Framework\Support\Auth\Jwt;

use Eyika\Atom\Framework\Support\Arr;
use App\Models\Role;
use App\Models\User;
use Hybridauth\Adapter\AdapterInterface;
use Hybridauth\Hybridauth;

final class JwtAuthenticator
{
    private const HEADER_VALUE_PATTERN = "/Bearer\s+(.*)$/i";

    private static $encoder;
    private static $user;

    // variables used for jwt
    private static $key;
    private static $iss;
    private static $aud;

    public function __construct(JwtEncoder $encoder, User $user)
    {
        static::$key = env('JWT_KEY');
        static::$iss = env('JWT_ISS');
        static::$aud = env('JWT_AUD');
        static::$encoder = $encoder;
        static::$user = $user;
    }

    /**
     * Verify the user role against an (array|string) of role(s)
     * 
     * @param User $user
     * @param array|string $_role
     * @param bool $return_bool
     * 
     * @return bool|User
     */
    public static function verifyRole($user, $_role, $return_bool = true)
    {
        new static(new JwtEncoder(env('APP_KEY')), $user);

        if (!is_array($_role)) {
            $_role = [$_role];
        }
        if (!$role = Role::getBuilder()->orderBy()->findBy('id', $user->role_id)) {
            return false;
        }
        if (Arr::exists($_role, $role[0]['name'], true)) {
            return true;
        }
        return false;
    }

    /**
     * validate function
     *
     * @return bool|User
     */
    public static function validate()
    {
        new static(new JwtEncoder(env('APP_KEY')), new User);
        $jwt = self::extractToken();
        if (empty($jwt)) {
            return false;
        }

        if (str_contains($jwt, "social_login:")) {
            $providers = ['facebook']; //, 'twitter', 'google'];
            $hybridauth = new Hybridauth("{$_SERVER['DOCUMENT_ROOT']}/../hybridauth_config.php");  //, null, new DbStorage('SOCIALAUTH::STORAGE'));

            foreach ($providers as $provider) {
                if ($hybridauth->isConnectedWith($provider)) {
                    $adapter = $hybridauth->getAdapter($provider);
                    break;
                }
                $adapter = null;
            }
            if ($adapter instanceof AdapterInterface) {
                if (!self::$user->find((int)base64_decode(str_replace('social_login:', '', $jwt), false))) {
                    return false;
                }
                $user_profile = $adapter->getUserProfile();
                if (self::$user->uuid !== $user_profile->identifier && self::$user->email !== $user_profile->email) {
                    return false;
                }
                return self::$user;
            }
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

    /**
     * validate function for firebase
     *
     * @param string $social_token
     * @return bool|string
     */
    public static function validateSocial($social_token)
    {
        $jwt = self::extractToken();
        if (empty($jwt)) {
            return false;
        }
        if (is_null($payload = self::$encoder->decode($jwt))) {
            return false;
        }

        $user = self::$user->fill((array)$payload->data);
        return self::authenticate($user, $social_token);
    }

    private static function extractToken(): ?string
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
     * uses firebase token to authenticate and generate a user's token
     *
     * @param User $user
     * @param string $password_or_token
     * @return string|bool
     */
    public static function authenticate(User $user, string $password_or_token = "")
    {
        new static(new JwtEncoder(env('APP_KEY')), $user);
        if ($password_or_token === "") {
            if (!self::validate()) {
                return false;
            }
        } else {
            if (!password_verify($password_or_token, $user->password)) {
                return false;
            }
        }

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
                "firstname" => $user->firstname,
                "lastname" => $user->lastname,
            ]
        ], self::$key);
        return $token;
    }
}
