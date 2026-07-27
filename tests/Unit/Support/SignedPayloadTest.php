<?php

namespace Eyika\Atom\Framework\Tests\Unit\Support;

use Eyika\Atom\Framework\Support\SignedPayload;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Covers SEC-23: queue/storage payloads are HMAC-signed, so a forged serialized
 * gadget-chain is rejected BEFORE unserialize() ever runs.
 */
class SignedPayloadTest extends TestCase
{
    public function test_sign_then_verify_roundtrips(): void
    {
        $value = ['id' => 7, 'name' => 'job', 'nested' => [1, 2, 3]];
        $this->assertSame($value, SignedPayload::verify(SignedPayload::sign($value)));
    }

    public function test_tampered_payload_is_rejected(): void
    {
        $payload = SignedPayload::sign(['id' => 1]);
        $tampered = substr($payload, 0, -1) . ($payload[-1] === 'A' ? 'B' : 'A');

        $this->expectException(RuntimeException::class);
        SignedPayload::verify($tampered);
    }

    public function test_unsigned_legacy_payload_is_rejected(): void
    {
        $this->expectException(RuntimeException::class);
        SignedPayload::verify(serialize(['id' => 1]));
    }

    public function test_forged_object_is_rejected_before_unserialize(): void
    {
        // A raw serialized object with no valid MAC must never reach unserialize().
        $this->expectException(RuntimeException::class);
        SignedPayload::verify('O:8:"stdClass":1:{s:3:"pwn";b:1;}');
    }
}
