<?php

namespace Basttyy\FxDataServer\libs;

use Exception;

class Encrypter
{
    protected $key;
    protected $cipher;

    public function __construct($key = null, $cipher = 'AES-256-CBC')
    {
        $this->key = $key ?? env('APP_KEY');
        $this->cipher = $cipher;
    }

    public function encrypt($value, $serialize = true)
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

    public function decrypt($payload, $unserialize = true)
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

    protected function getJsonPayload($payload)
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
