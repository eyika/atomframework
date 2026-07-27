<?php

namespace Eyika\Atom\Framework\Tests\Unit\Http;

use Eyika\Atom\Framework\Http\Response;
use PHPUnit\Framework\TestCase;

/**
 * Covers the setCookie() arg-order fix: path/domain were passed to Cookie in
 * swapped positions, and the absolute $expiry timestamp was fed into the maxAge
 * (duration) slot.
 */
class ResponseCookieTest extends TestCase
{
    public function test_set_cookie_maps_path_and_domain_to_correct_attributes(): void
    {
        $response = new Response();
        $response->setCookie('sid', 'v', time() + 3600, '/app', 'example.com', true, true);

        $cookie = $response->cookies()->get('sid');
        $this->assertNotNull($cookie);
        $this->assertSame('/app', $cookie->getPath());        // was receiving the domain
        $this->assertSame('example.com', $cookie->getDomain()); // was receiving the path
    }

    public function test_set_cookie_absolute_expiry_becomes_future_expires_and_maxage(): void
    {
        $response = new Response();
        $response->setCookie('sid', 'v', time() + 3600, '/');

        $str = $response->cookies()->get('sid')->toString();
        $this->assertStringNotContainsString('1970', $str);
        $this->assertStringContainsString('Expires=', $str);

        preg_match('/Max-Age=(\d+)/', $str, $m);
        $this->assertNotEmpty($m[1] ?? null);
        // ~3600s remaining (allow slack for execution time).
        $this->assertGreaterThan(3500, (int) $m[1]);
        $this->assertLessThanOrEqual(3600, (int) $m[1]);
    }

    public function test_set_cookie_defaults_to_httponly_and_samesite_lax(): void
    {
        $response = new Response();
        $response->setCookie('sid', 'v', time() + 60);

        $str = $response->cookies()->get('sid')->toString();
        $this->assertStringContainsString('HttpOnly', $str);
        $this->assertStringContainsString('SameSite=Lax', $str);
    }
}
