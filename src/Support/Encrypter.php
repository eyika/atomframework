<?php

namespace Eyika\Atom\Framework\Support;

use Exception;

class Encrypter
{
    /** Key length, in bytes, that each supported cipher requires. */
    protected const SUPPORTED_CIPHERS = [
        'aes-128-cbc' => 16,
        'aes-256-cbc' => 32,
    ];

    protected $key;
    protected $cipher;

    public function __construct($key = null, $cipher = 'AES-256-CBC')
    {
        $decoded = static::decodeKey((string) ($key ?? env('APP_KEY')));

        $required = static::SUPPORTED_CIPHERS[strtolower($cipher)] ?? null;

        if ($required === null) {
            throw new Exception(
                "Unsupported cipher [{$cipher}]. Supported ciphers are: "
                . implode(', ', array_keys(static::SUPPORTED_CIPHERS)) . '.'
            );
        }

        $length = mb_strlen($decoded, '8bit');

        if ($length !== $required) {
            // Never echo the key itself — only its length.
            throw new Exception(
                "The application key must be {$required} bytes for [{$cipher}], got {$length}. "
                . 'Generate a valid key with `php artisan key:generate`.'
            );
        }

        $this->key    = $decoded;
        $this->cipher = $cipher;
    }

    /**
     * Decode a `base64:` prefixed key to its raw bytes; other keys are used as-is.
     */
    protected static function decodeKey(string $key): string
    {
        if (!str_starts_with($key, 'base64:')) {
            return $key;
        }

        $decoded = base64_decode(substr($key, 7), true);

        if ($decoded === false) {
            throw new Exception('The application key is prefixed `base64:` but is not valid base64.');
        }

        return $decoded;
    }

    public function encrypt(mixed $value, bool $serialize = false): string
    {
        $iv = random_bytes(openssl_cipher_iv_length($this->cipher));

        $value = openssl_encrypt(
            $serialize ? serialize($value) : $value,
            $this->cipher, $this->key, 0, $iv
        );

        if ($value === false) {
            throw new Exception('Could not encrypt the data.');
        }

        // The "payload" is the final encrypted value, along with the IV and a
        // HMAC for integrity checking.
        $mac = $this->hash($iv = base64_encode($iv), $value);

        $json = json_encode(compact('iv', 'value', 'mac'));

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Could not encrypt the data.');
        }

        return base64_encode($json);
    }

    public function decrypt(string $payload, bool $unserialize = false): mixed
    {
        $payload = $this->getJsonPayload($payload);

        $iv = base64_decode($payload['iv']);

        $decrypted = openssl_decrypt(
            $payload['value'], $this->cipher, $this->key, 0, $iv
        );

        if ($decrypted === false) {
            throw new Exception('Could not decrypt the data.');
        }

        return $unserialize ? unserialize($decrypted) : $decrypted;
    }

    protected function getJsonPayload(string $payload)
    {
        $payload = json_decode(base64_decode($payload), true);

        if (!$this->validPayload($payload)) {
            throw new Exception('The payload is invalid.');
        }

        if (!$this->validMac($payload)) {
            throw new Exception('The MAC is invalid.');
        }

        return $payload;
    }

    protected function validPayload($payload)
    {
        return is_array($payload) && isset($payload['iv'], $payload['value'], $payload['mac']);
    }

    protected function validMac(array $payload)
    {
        $calculated = $this->hash($payload['iv'], $payload['value']);

        return hash_equals($payload['mac'], $calculated);
    }

    protected function hash($iv, $value)
    {
        return hash_hmac('sha256', $iv.$value, $this->key);
    }
}
