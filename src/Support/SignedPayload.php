<?php

namespace Eyika\Atom\Framework\Support;

use RuntimeException;

/**
 * HMAC-signed serialization envelope for values that are persisted and later
 * unserialize()d (queue jobs, durable object storage). Signing with app.key means
 * an attacker who can write the store (e.g. via another bug) cannot smuggle a
 * serialized gadget-chain: verify() rejects any payload whose MAC doesn't match
 * BEFORE unserialize() runs, so __wakeup/__destruct gadgets never fire.
 *
 * Envelope: "<hmac-sha256-hex>|<base64(serialize(value))>".
 */
final class SignedPayload
{
    private const SEP = '|';

    public static function sign(mixed $value): string
    {
        $data = base64_encode(serialize($value));
        return hash_hmac('sha256', $data, self::key()) . self::SEP . $data;
    }

    /**
     * Verify the signature and return the unserialized value.
     *
     * @throws RuntimeException if the payload is unsigned, malformed, or tampered.
     */
    public static function verify(string $payload): mixed
    {
        $pos = strpos($payload, self::SEP);
        if ($pos === false) {
            throw new RuntimeException('Rejected unsigned/malformed payload.');
        }

        $mac = substr($payload, 0, $pos);
        $data = substr($payload, $pos + 1);

        if (!hash_equals(hash_hmac('sha256', $data, self::key()), $mac)) {
            throw new RuntimeException('Payload signature verification failed.');
        }

        return unserialize(base64_decode($data));
    }

    private static function key(): string
    {
        $key = (string) config('app.key');
        if ($key === '') {
            throw new RuntimeException('app.key must be configured to sign/verify payloads.');
        }
        return $key;
    }
}
