<?php

namespace Eyika\Atom\Framework\Tests\Unit\Auth;

use Eyika\Atom\Framework\Support\Auth\Guards\JwtGuards\JwtGuard;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Covers SEC-20 (issuer/audience verified when configured) and SEC-21 (the bearer
 * token is extracted raw, not run through sanitize_data()).
 */
class JwtGuardTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_ENV['JWT_ISS'], $_ENV['JWT_AUD'], $_SERVER['HTTP_AUTHORIZATION']);
        parent::tearDown();
    }

    public function test_extract_token_returns_raw_bearer_unmangled(): void
    {
        // Chars that strip_tags/htmlspecialchars could touch must survive intact.
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer abc.DEF-ghi_jkl.mno';

        $m = new ReflectionMethod(JwtGuard::class, 'extractToken');
        $m->setAccessible(true);

        $this->assertSame('abc.DEF-ghi_jkl.mno', $m->invoke(null));
    }

    public function test_extract_token_null_when_no_header(): void
    {
        $m = new ReflectionMethod(JwtGuard::class, 'extractToken');
        $m->setAccessible(true);

        $this->assertNull($m->invoke(null));
    }

    public function test_claims_valid_enforces_iss_and_aud_when_configured(): void
    {
        $_ENV['JWT_ISS'] = 'my-iss';
        $_ENV['JWT_AUD'] = 'my-aud';
        $guard = new JwtGuard([], 'api');

        $m = new ReflectionMethod($guard, 'claimsValid');
        $m->setAccessible(true);

        $this->assertTrue($m->invoke($guard, (object) ['iss' => 'my-iss', 'aud' => 'my-aud']));
        $this->assertFalse($m->invoke($guard, (object) ['iss' => 'evil', 'aud' => 'my-aud']));
        $this->assertFalse($m->invoke($guard, (object) ['iss' => 'my-iss', 'aud' => 'evil']));
        $this->assertFalse($m->invoke($guard, (object) [])); // missing claims when required
    }

    public function test_claims_skipped_when_not_configured(): void
    {
        unset($_ENV['JWT_ISS'], $_ENV['JWT_AUD']);
        $guard = new JwtGuard([], 'api');

        $m = new ReflectionMethod($guard, 'claimsValid');
        $m->setAccessible(true);

        $this->assertTrue($m->invoke($guard, (object) []));
    }
}
