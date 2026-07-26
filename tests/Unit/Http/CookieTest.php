<?php

namespace Eyika\Atom\Framework\Tests\Unit\Http;

use Eyika\Atom\Framework\Http\Cookie;
use PHPUnit\Framework\TestCase;

/**
 * Covers SEC-01 / SEC-02: cookies must serialize to a real Set-Cookie string with
 * correct Expires/Max-Age (derived from the maxAge DURATION, not treated as an
 * absolute epoch) and a SameSite attribute + flags.
 */
class CookieTest extends TestCase
{
    public function test_to_string_emits_future_expiry_from_max_age_duration(): void
    {
        $cookie = new Cookie('session', 'abc', 3600, null, '/', true, true);
        $str = $cookie->toString();

        // Name/value.
        $this->assertStringStartsWith('session=abc', $str);

        // Expires must be ~1 hour in the FUTURE — the old code fed maxAge (3600) to
        // gmdate() as an epoch, producing "Thu, 01-Jan-1970 01:00:00".
        $this->assertStringNotContainsString('1970', $str);
        $this->assertMatchesRegularExpression('/Max-Age=3600\b/', $str);
        $this->assertStringContainsString('Expires=', $str);

        // Attributes/flags.
        $this->assertStringContainsString('Path=/', $str);
        $this->assertStringContainsString('Secure', $str);
        $this->assertStringContainsString('HttpOnly', $str);
        $this->assertStringContainsString('SameSite=Lax', $str);
    }

    public function test_expiry_timestamp_is_roughly_now_plus_max_age(): void
    {
        $cookie = new Cookie('k', 'v', 120);
        preg_match('/Expires=([^;]+)/', $cookie->toString(), $m);
        $this->assertNotEmpty($m[1] ?? null);

        $expiresTs = strtotime($m[1]);
        $this->assertGreaterThan(time() + 100, $expiresTs);
        $this->assertLessThan(time() + 140, $expiresTs);
    }

    public function test_flags_absent_when_not_set(): void
    {
        $cookie = new Cookie('k', 'v', 60, null, '/', false, false);
        $str = $cookie->toString();

        $this->assertStringNotContainsString('Secure', $str);
        $this->assertStringNotContainsString('HttpOnly', $str);
    }

    public function test_same_site_is_normalized_and_defaults_to_lax(): void
    {
        $this->assertSame('Lax', (new Cookie('k', 'v'))->getSameSite());
        $this->assertSame('Strict', (new Cookie('k', 'v', 60, null, null, false, false, null, 'strict'))->getSameSite());
        $this->assertSame('None', (new Cookie('k', 'v', 60, null, null, false, false, null, 'NONE'))->getSameSite());
        // Invalid value → omitted (empty).
        $this->assertSame('', (new Cookie('k', 'v', 60, null, null, false, false, null, 'bogus'))->getSameSite());
    }

    public function test_with_same_site_returns_clone_without_mutating_original(): void
    {
        $original = new Cookie('k', 'v');
        $modified = $original->withSameSite('Strict');

        $this->assertSame('Lax', $original->getSameSite());
        $this->assertSame('Strict', $modified->getSameSite());
        $this->assertNotSame($original, $modified);
    }

    public function test_domain_only_included_when_set(): void
    {
        $this->assertStringNotContainsString('Domain=', (new Cookie('k', 'v', 60))->toString());
        $this->assertStringContainsString('Domain=example.com', (new Cookie('k', 'v', 60, 'example.com'))->toString());
    }
}
