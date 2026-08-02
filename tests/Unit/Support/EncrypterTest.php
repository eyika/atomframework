<?php

namespace Eyika\Atom\Framework\Tests\Unit\Support;

use Eyika\Atom\Framework\Support\Encrypter;
use PHPUnit\Framework\TestCase;

/**
 * Covers SEC-26. The Encrypter used the configured APP_KEY verbatim: no `base64:` decoding and
 * no length assertion. Because `key:generate` writes `APP_KEY=base64:…`, openssl silently
 * truncated that 51-character string to 32 bytes, so the effective AES-256 key began with the
 * constant bytes `base64:` and carried far less entropy than intended.
 *
 * Decoding the prefix changes the key, so payloads written under the old behaviour would stop
 * opening — including encrypted columns. The legacy-key fallback below is what prevents that.
 */
class EncrypterTest extends TestCase
{
    /** A valid 32-raw-byte key in the format key:generate emits. */
    private function base64Key(): string
    {
        return 'base64:' . base64_encode(str_repeat("\x2b", 32));
    }

    public function test_round_trips_with_a_raw_32_byte_key(): void
    {
        $e = new Encrypter(str_repeat('a', 32));

        $this->assertSame('hello', $e->decrypt($e->encrypt('hello')));
    }

    public function test_round_trips_with_a_base64_prefixed_key(): void
    {
        $e = new Encrypter($this->base64Key());

        $this->assertSame('hello', $e->decrypt($e->encrypt('hello')));
    }

    public function test_base64_prefix_is_decoded_to_raw_bytes(): void
    {
        // Same 32 underlying bytes expressed both ways must yield interchangeable ciphertext.
        $raw    = str_repeat("\x2b", 32);
        $prefixed = new Encrypter('base64:' . base64_encode($raw));
        $direct   = new Encrypter($raw);

        $this->assertSame('shared', $direct->decrypt($prefixed->encrypt('shared')));
    }

    public function test_serialized_values_round_trip(): void
    {
        $e = new Encrypter($this->base64Key());
        $value = ['a' => 1, 'b' => [2, 3]];

        $this->assertSame($value, $e->decrypt($e->encrypt($value, true), true));
    }

    /**
     * There is deliberately NO fallback to the old key: a payload written under the previous
     * behaviour (the whole `base64:…` string handed verbatim to openssl and hash_hmac) must be
     * REJECTED, not silently opened with the weak key. Apps holding such data migrate it with a
     * one-shot re-encryption command — see the key-rotation guide in the docs.
     *
     * This also pins the exact legacy ciphertext construction that a migration must reproduce.
     */
    public function test_rejects_legacy_payloads_written_before_base64_decoding(): void
    {
        $configured = $this->base64Key();

        $iv = random_bytes(16);
        $value = openssl_encrypt('legacy secret', 'AES-256-CBC', $configured, 0, $iv);
        $iv = base64_encode($iv);
        $mac = hash_hmac('sha256', $iv . $value, $configured);
        $legacyPayload = base64_encode(json_encode(compact('iv', 'value', 'mac')));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('The MAC is invalid.');

        (new Encrypter($configured))->decrypt($legacyPayload);
    }

    public function test_payloads_are_authenticated_with_the_decoded_key(): void
    {
        $configured = $this->base64Key();
        $fresh = (new Encrypter($configured))->encrypt('written today');

        // The decoded key opens it; the legacy (undecoded) key must not.
        $decoded = new Encrypter(base64_decode(substr($configured, 7)));
        $this->assertSame('written today', $decoded->decrypt($fresh));

        $payload = json_decode(base64_decode($fresh), true);
        $this->assertNotSame(
            hash_hmac('sha256', $payload['iv'] . $payload['value'], $configured),
            $payload['mac'],
            'new payloads must be authenticated with the decoded key'
        );
    }

    public function test_rejects_a_key_of_the_wrong_length(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('must be 32 bytes');

        new Encrypter('too-short');
    }

    public function test_rejects_an_unsupported_cipher(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Unsupported cipher');

        new Encrypter(str_repeat('a', 32), 'AES-999-XYZ');
    }

    public function test_rejects_a_malformed_base64_key(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('not valid base64');

        new Encrypter('base64:!!!not-base64!!!');
    }

    public function test_tampered_payload_is_rejected(): void
    {
        $e = new Encrypter($this->base64Key());
        $payload = json_decode(base64_decode($e->encrypt('trusted')), true);
        $payload['value'] = base64_encode('tampered');

        $this->expectException(\Exception::class);
        $e->decrypt(base64_encode(json_encode($payload)));
    }
}
