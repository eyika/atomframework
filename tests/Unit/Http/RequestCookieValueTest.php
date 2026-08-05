<?php

namespace Eyika\Atom\Framework\Tests\Unit\Http;

use Eyika\Atom\Framework\Http\Cookie;
use Eyika\Atom\Framework\Http\Request;
use PHPUnit\Framework\TestCase;

/**
 * Reported by Claude C (vendra): `cookie()` returned a Cookie OBJECT while `query()`/`input()`
 * return the value, so the obvious line
 *
 *     $token = $request->cookie('cart_token') ?? $request->query('cart_token');
 *
 * was string-or-object depending on which branch hit. Guarded by `is_string($token)` — as their
 * storefront was — the cookie path is always false, so the cookie silently never worked.
 *
 * Reading and writing are different jobs: a `Cookie` describes a Set-Cookie header (path, domain,
 * SameSite, expiry), and none of those exist on an inbound cookie — the browser sends only
 * `name=value`. So `cookie()` now returns the value, matching Laravel, and `cookieObject()` is
 * there for the rare caller that wants the wrapper.
 */
class RequestCookieValueTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/';
        $_COOKIE = ['cart_token' => 'abc123', 'sid' => 'xyz'];
        $_GET = ['cart_token' => 'abc123'];
    }

    protected function tearDown(): void
    {
        $_COOKIE = [];
        $_GET = [];
        $_SERVER = [];
        parent::tearDown();
    }

    public function test_cookie_returns_the_value_not_the_wrapper(): void
    {
        $this->assertSame('abc123', (new Request())->cookie('cart_token'));
    }

    /**
     * The property everyone assumes and nothing checked: the same value read through two
     * accessors comes back as the same type.
     */
    public function test_cookie_and_query_return_the_same_type_for_the_same_value(): void
    {
        $request = new Request();

        $fromCookie = $request->cookie('cart_token');
        $fromQuery  = $request->query('cart_token');

        $this->assertSame(gettype($fromQuery), gettype($fromCookie));
        $this->assertSame($fromQuery, $fromCookie);
    }

    /** The exact line from the report — it must not change type depending on which branch hits. */
    public function test_the_natural_coalescing_line_yields_a_string_either_way(): void
    {
        $request = new Request();

        $fromCookie = $request->cookie('cart_token') ?? $request->query('cart_token');
        $this->assertIsString($fromCookie);

        $_COOKIE = [];
        $queryOnly = (new Request())->cookie('cart_token') ?? $request->query('cart_token');
        $this->assertIsString($queryOnly);
    }

    /** `is_string()` is what silently swallowed the cookie path in the field. */
    public function test_a_defensive_is_string_guard_now_passes(): void
    {
        $token = (new Request())->cookie('cart_token');

        $this->assertTrue(is_string($token));
    }

    public function test_missing_cookie_returns_the_default(): void
    {
        $request = new Request();

        $this->assertNull($request->cookie('nope'));
        $this->assertSame('fallback', $request->cookie('nope', 'fallback'));
    }

    /** A null key must give name => value, not an array of objects. */
    public function test_all_cookies_are_returned_as_name_value_pairs(): void
    {
        $all = (new Request())->cookie();

        $this->assertSame(['cart_token' => 'abc123', 'sid' => 'xyz'], $all);
    }

    public function test_all_cookies_json_encode_as_a_flat_map(): void
    {
        $this->assertSame(
            '{"cart_token":"abc123","sid":"xyz"}',
            json_encode((new Request())->cookie())
        );
    }

    // ---------------------------------------------------------------- the wrapper escape hatch

    public function test_cookie_object_returns_the_wrapper(): void
    {
        $cookie = (new Request())->cookieObject('cart_token');

        $this->assertInstanceOf(Cookie::class, $cookie);
        $this->assertSame('abc123', $cookie->getValue());
        $this->assertSame('cart_token', $cookie->getName());
    }

    public function test_cookie_object_returns_null_when_absent(): void
    {
        $this->assertNull((new Request())->cookieObject('nope'));
    }

    /** `cookies()` is the object collection and is honestly named — it keeps its shape. */
    public function test_cookies_collection_still_holds_objects(): void
    {
        $this->assertInstanceOf(Cookie::class, (new Request())->cookies()->get('cart_token'));
    }
}
