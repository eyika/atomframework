<?php

namespace Eyika\Atom\Framework\Tests\Unit\Http;

use Eyika\Atom\Framework\Http\Request;
use PHPUnit\Framework\TestCase;

/**
 * Reported by Claude C (vendra). `__set` writes to the attribute bag, but `__get` used to check
 * that bag LAST — after input, route params and query — so anything trusted server-side code
 * bound could be shadowed by a request parameter of the same name.
 *
 * The route-param case cost them a batch of 500s: a `{business}` param shadowed a
 * middleware-bound `$request->business`, so the handler got the raw URL segment instead of the
 * object. The escalation is the security one: on an UNAUTHENTICATED route a client could shadow
 * bound context just by naming it in the body — `$request->current_customer` returned whatever
 * the caller posted under that key.
 *
 * Attributes are now resolved first. Route params still outrank input (they are matched from the
 * path, not sent as a payload).
 */
class RequestAttributePrecedenceTest extends TestCase
{
    private function request(array $source = []): Request
    {
        return new Request($source + [
            'server'  => ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/'],
            'query'   => [],
            'post'    => [],
            'cookies' => [],
            'files'   => [],
            'headers' => [],
        ]);
    }

    /** The security case: a client must not be able to shadow server-bound context. */
    public function test_a_bound_attribute_beats_client_supplied_body_input(): void
    {
        $request = $this->request(['post' => ['current_customer' => 'attacker-controlled']]);

        $bound = new \stdClass();
        $bound->id = 7;
        $request->current_customer = $bound;

        $this->assertSame($bound, $request->current_customer, 'body input must not shadow a bound attribute');
    }

    public function test_a_bound_attribute_beats_a_query_string_value(): void
    {
        $request = $this->request(['query' => ['tenant' => 'spoofed']]);

        $request->tenant = 'bound-by-middleware';

        $this->assertSame('bound-by-middleware', $request->tenant);
    }

    /** The originally reported case: a route param shadowing bound context. */
    public function test_a_bound_attribute_beats_a_route_param(): void
    {
        $request = $this->request();
        $request->route_params = ['business' => '42'];

        $business = new \stdClass();
        $business->id = 42;
        $request->business = $business;

        $this->assertSame($business, $request->business, 'a route param must not shadow a bound object');
    }

    /**
     * A SECOND behaviour change, beyond the reported one: input used to be checked before route
     * params, so a body field could shadow the matched path segment — `/users/{id}` with `id` in
     * the body handed the handler the body's value. That is the same shadowing family as the
     * reported bug (client payload overriding something resolved by the server), so route params
     * now win. Both are client-supplied, but the path segment at least had to match the route.
     */
    public function test_route_params_now_outrank_body_input(): void
    {
        $request = $this->request(['post' => ['id' => 'from-body']]);
        $request->route_params = ['id' => 'from-path'];

        $this->assertSame('from-path', $request->id);
    }

    public function test_input_is_still_returned_when_nothing_else_defines_the_key(): void
    {
        $request = $this->request(['post' => ['note' => 'hello']]);

        $this->assertSame('hello', $request->note);
    }

    public function test_query_is_still_returned_when_nothing_else_defines_the_key(): void
    {
        $request = $this->request(['query' => ['page' => '3']]);

        $this->assertSame('3', $request->page);
    }

    public function test_an_unknown_key_is_null(): void
    {
        $this->assertNull($this->request()->nothing_here);
    }

    // --- the explicit API, which sidesteps precedence entirely ----------------------------

    public function test_the_explicit_attribute_api_ignores_input_and_route_params(): void
    {
        $request = $this->request(['post' => ['ctx' => 'from-body']]);
        $request->route_params = ['ctx' => 'from-path'];

        $request->setAttribute('ctx', 'bound');

        $this->assertSame('bound', $request->attribute('ctx'));
        $this->assertTrue($request->hasAttribute('ctx'));
        $this->assertSame(['ctx' => 'bound'], $request->attributes());
    }

    public function test_attribute_returns_the_default_when_unbound(): void
    {
        $request = $this->request(['post' => ['ctx' => 'from-body']]);

        // Present as input, but NOT bound — the explicit reader must not fall through to it.
        $this->assertFalse($request->hasAttribute('ctx'));
        $this->assertSame('fallback', $request->attribute('ctx', 'fallback'));
    }

    public function test_set_attribute_is_chainable_and_equivalent_to_the_magic_setter(): void
    {
        $request = $this->request();

        $request->setAttribute('a', 1)->setAttribute('b', 2);
        $request->c = 3;

        $this->assertSame(['a' => 1, 'b' => 2, 'c' => 3], $request->attributes());
    }
}
