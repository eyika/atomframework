<?php

namespace Eyika\Atom\Framework\Tests\Feature;

use Eyika\Atom\Framework\Http\Request;
use Eyika\Atom\Framework\Support\Auth\Guards\SessionGuard;

/**
 * Covers SEC-03: the remember-me cookie must be encrypt-then-MAC protected, so a
 * forged value can never authenticate. recall() decrypts+verifies and returns null
 * on any tampering.
 */
class RememberMeTest extends IntegrationTestCase
{
    private function bindRequestWithCookies(array $cookies): void
    {
        $_COOKIE = $cookies;
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/';
        $_SERVER['HTTP_HOST'] = 'localhost';
        $this->app->instance('request', new Request());
    }

    public function test_recall_rejects_a_forged_cookie(): void
    {
        // Attacker tries to assert "I am user 1" — base64 keeps it a valid cookie
        // value but it is not a real encrypt()-produced token, so the MAC check fails.
        $this->bindRequestWithCookies(['auth_remember' => base64_encode(json_encode(['id' => 1]))]);

        $guard = new SessionGuard([], 'web');
        $this->assertNull($guard->recall());
    }

    public function test_recall_returns_null_without_a_cookie(): void
    {
        $this->bindRequestWithCookies([]);

        $guard = new SessionGuard([], 'web');
        $this->assertNull($guard->recall());
    }

    public function test_encrypted_remember_token_is_not_plaintext_and_roundtrips(): void
    {
        $payload = json_encode(['id' => 42, 'v' => 1]);
        $token = encrypt($payload);

        $this->assertNotSame($payload, $token);
        $this->assertStringNotContainsString('42', $token);

        $data = json_decode(decrypt($token), true);
        $this->assertSame(42, $data['id']);
    }
}
