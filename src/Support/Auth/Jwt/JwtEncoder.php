<?php

namespace Eyika\Atom\Framework\Support\Auth\Jwt;

use Exception;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

final class JwtEncoder
{
    private $key;

    public function __construct(string $key)
    {
        $this->key = $key;
    }

    public function encode(array $payload): string
    {
        return JWT::encode($payload, $this->key, "HS256");
    }

    public function decode(string $jwt): object|null
    {
        try {
            $decoded = JWT::decode($jwt, new Key($this->key, "HS256"));
            return $decoded;
        } catch (Exception $ex) {
            return null;
        }
    }
}