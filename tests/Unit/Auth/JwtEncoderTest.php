<?php

namespace Eyika\Atom\Framework\Tests\Unit\Auth;

use Eyika\Atom\Framework\Support\Auth\Guards\JwtGuards\JwtEncoder;
use PHPUnit\Framework\TestCase;

/**
 * Covers SEC-19: signing is consistent and a token signed with a different key is
 * rejected (so the signing key actually protects the token).
 */
class JwtEncoderTest extends TestCase
{
    private string $key = 'signing-key-0123456789abcdef0123456789ab';

    public function test_encode_decode_roundtrip(): void
    {
        $encoder = new JwtEncoder($this->key);
        $token = $encoder->encode(['data' => ['id' => 7], 'exp' => time() + 3600]);

        $decoded = $encoder->decode($token);
        $this->assertNotNull($decoded);
        $this->assertSame(7, $decoded->data->id);
    }

    public function test_decode_with_wrong_key_is_rejected(): void
    {
        $token = (new JwtEncoder($this->key))->encode(['data' => ['id' => 7], 'exp' => time() + 3600]);

        $this->assertNull((new JwtEncoder('a-completely-different-key-0000000000'))->decode($token));
    }

    public function test_decode_garbage_returns_null(): void
    {
        $this->assertNull((new JwtEncoder($this->key))->decode('not.a.jwt'));
    }
}
