<?php

namespace Eyika\Atom\Framework\Tests\Unit\Http;

use Eyika\Atom\Framework\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Reported by Claude A (backtestfx): the trusted-proxy API advertised a feature it did not have.
 *
 *  1. HEADER_X_FORWARDED_* were `$_SERVER` key STRINGS carrying the names of Symfony's bit flags,
 *     so the documented `A | B` usage produced a byte-wise-OR'd binary string, not an int —
 *     `'HTTP_X_FORWARDED_FOR' | 'HTTP_X_FORWARDED_HOST' | …` evaluates to "HTTP_X_FORWARDED_^__TO".
 *     Passing that to `setTrustedProxies(array, int|null)` is a TypeError.
 *  2. The `$headers` parameter was stored in `proxyheader` and read nowhere, so header selection
 *     did not exist; every forwarded header was believed once the peer was trusted.
 *  3. `isFromTrustedProxy()` was a bare `in_array()`, so a CIDR entry silently matched nothing.
 *
 * They are now real flags that gate each header independently.
 */
class TrustedProxyTest extends TestCase
{
    /** A request whose peer is $remoteAddr, carrying the given forwarded headers. */
    private function request(string $remoteAddr, array $server = []): Request
    {
        $_SERVER = array_merge([
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI'    => '/',
            'HTTP_HOST'      => 'app.example.com',
            'SERVER_PORT'    => '80',
            'REMOTE_ADDR'    => $remoteAddr,
        ], $server);

        return new Request();
    }

    protected function tearDown(): void
    {
        $_SERVER = [];
        parent::tearDown();
    }

    // ---------------------------------------------------------------- flags are real ints

    public function test_the_constants_are_integers_that_can_be_combined(): void
    {
        $this->assertIsInt(Request::HEADER_X_FORWARDED_FOR);

        $combined = Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_HOST
                  | Request::HEADER_X_FORWARDED_PORT | Request::HEADER_X_FORWARDED_PROTO;

        $this->assertIsInt($combined);
        $this->assertSame(Request::HEADER_X_FORWARDED_ALL, $combined);
    }

    /** The exact expression from the shipped scaffold must now be accepted, not a TypeError. */
    public function test_the_scaffold_expression_is_accepted_by_set_trusted_proxies(): void
    {
        $headers = Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_HOST
                 | Request::HEADER_X_FORWARDED_PORT | Request::HEADER_X_FORWARDED_PROTO;

        $request = $this->request('10.0.0.1');
        $request->setTrustedProxies(['10.0.0.1'], $headers);

        $this->assertSame(Request::HEADER_X_FORWARDED_ALL, $request->trustedHeaderSet());
    }

    /** Each flag must be a distinct single bit, or masking silently conflates two headers. */
    public function test_each_flag_is_a_distinct_single_bit(): void
    {
        $flags = [
            Request::HEADER_X_FORWARDED_FOR,
            Request::HEADER_X_FORWARDED_HOST,
            Request::HEADER_X_FORWARDED_PROTO,
            Request::HEADER_X_FORWARDED_PORT,
        ];

        $this->assertSame($flags, array_unique($flags));
        foreach ($flags as $flag) {
            $this->assertSame(0, $flag & ($flag - 1), "flag $flag is not a single bit");
        }
    }

    // ---------------------------------------------------------------- untrusted peers

    public function test_forwarded_headers_are_ignored_without_any_trusted_proxy(): void
    {
        $request = $this->request('203.0.113.9', [
            'HTTP_X_FORWARDED_FOR'   => '1.2.3.4',
            'HTTP_X_FORWARDED_HOST'  => 'evil.example.com',
            'HTTP_X_FORWARDED_PROTO' => 'https',
        ]);

        $this->assertFalse($request->isFromTrustedProxy());
        $this->assertSame('203.0.113.9', $request->clientIp());
        $this->assertSame('app.example.com', $request->host());
        $this->assertSame('http', $request->scheme());
    }

    public function test_a_peer_outside_the_trusted_list_is_not_trusted(): void
    {
        $request = $this->request('203.0.113.9', ['HTTP_X_FORWARDED_HOST' => 'evil.example.com']);
        $request->setTrustedProxies(['10.0.0.1']);

        $this->assertFalse($request->isFromTrustedProxy());
        $this->assertSame('app.example.com', $request->host());
    }

    // ---------------------------------------------------------------- per-header selection

    /**
     * The point of the whole exercise: a proxy that sets XFF but never XFH is believed for the
     * former only, so a client cannot choose the host the app resolves tenants from.
     */
    public function test_trusting_for_only_does_not_trust_host_or_proto(): void
    {
        $request = $this->request('10.0.0.1', [
            'HTTP_X_FORWARDED_FOR'   => '1.2.3.4',
            'HTTP_X_FORWARDED_HOST'  => 'evil.example.com',
            'HTTP_X_FORWARDED_PROTO' => 'https',
        ]);
        $request->setTrustedProxies(['10.0.0.1'], Request::HEADER_X_FORWARDED_FOR);

        $this->assertSame('1.2.3.4', $request->clientIp(), 'XFF was trusted, so it should apply');
        $this->assertSame('app.example.com', $request->host(), 'XFH was NOT trusted');
        $this->assertSame('http', $request->scheme(), 'XFP was NOT trusted');
    }

    public function test_trusting_host_only_does_not_trust_for(): void
    {
        $request = $this->request('10.0.0.1', [
            'HTTP_X_FORWARDED_FOR'  => '1.2.3.4',
            'HTTP_X_FORWARDED_HOST' => 'tenant.example.com',
        ]);
        $request->setTrustedProxies(['10.0.0.1'], Request::HEADER_X_FORWARDED_HOST);

        $this->assertSame('tenant.example.com', $request->host());
        $this->assertSame('10.0.0.1', $request->clientIp());
    }

    public function test_null_headers_means_trust_all_of_them(): void
    {
        $request = $this->request('10.0.0.1', [
            'HTTP_X_FORWARDED_FOR'   => '1.2.3.4',
            'HTTP_X_FORWARDED_HOST'  => 'tenant.example.com',
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_X_FORWARDED_PORT'  => '8443',
        ]);
        $request->setTrustedProxies(['10.0.0.1']);

        $this->assertSame('1.2.3.4', $request->clientIp());
        $this->assertSame('tenant.example.com', $request->host());
        $this->assertSame('https', $request->scheme());
        $this->assertSame(8443, $request->port());
    }

    /** Zero is meaningfully different from null — trust the peer's identity for nothing. */
    public function test_zero_headers_trusts_the_proxy_for_nothing(): void
    {
        $request = $this->request('10.0.0.1', [
            'HTTP_X_FORWARDED_FOR'  => '1.2.3.4',
            'HTTP_X_FORWARDED_HOST' => 'evil.example.com',
        ]);
        $request->setTrustedProxies(['10.0.0.1'], 0);

        $this->assertTrue($request->isFromTrustedProxy());
        $this->assertSame('10.0.0.1', $request->clientIp());
        $this->assertSame('app.example.com', $request->host());
    }

    // ---------------------------------------------------------------- CIDR

    #[DataProvider('cidrProvider')]
    public function test_cidr_entries_match_the_addresses_they_cover(string $peer, string $entry, bool $expected): void
    {
        $request = $this->request($peer);
        $request->setTrustedProxies([$entry]);

        $this->assertSame($expected, $request->isFromTrustedProxy());
    }

    public static function cidrProvider(): array
    {
        return [
            'v4 inside /8'            => ['10.4.5.6', '10.0.0.0/8', true],
            'v4 outside /8'           => ['11.4.5.6', '10.0.0.0/8', false],
            'v4 inside /24'           => ['192.168.1.77', '192.168.1.0/24', true],
            'v4 outside /24'          => ['192.168.2.77', '192.168.1.0/24', false],
            'v4 /32 is exact'         => ['192.168.1.1', '192.168.1.1/32', true],
            'v4 /32 excludes others'  => ['192.168.1.2', '192.168.1.1/32', false],
            'v4 /0 matches anything'  => ['8.8.8.8', '0.0.0.0/0', true],
            'non-boundary /12 in'     => ['172.16.5.4', '172.16.0.0/12', true],
            'non-boundary /12 out'    => ['172.32.5.4', '172.16.0.0/12', false],
            'v6 inside /32'           => ['2001:db8::1', '2001:db8::/32', true],
            'v6 outside /32'          => ['2001:db9::1', '2001:db8::/32', false],
            'v4 peer vs v6 block'     => ['10.0.0.1', '2001:db8::/32', false],
            'v6 peer vs v4 block'     => ['2001:db8::1', '10.0.0.0/8', false],
            'literal still works'     => ['10.0.0.1', '10.0.0.1', true],
            'malformed prefix'        => ['10.0.0.1', '10.0.0.0/abc', false],
            'out-of-range prefix'     => ['10.0.0.1', '10.0.0.0/99', false],
        ];
    }

    /** Explicitly opting into "any upstream" is honoured rather than silently ignored. */
    public function test_wildcard_trusts_the_connecting_peer(): void
    {
        $request = $this->request('203.0.113.9', ['HTTP_X_FORWARDED_FOR' => '1.2.3.4']);
        $request->setTrustedProxies(['*']);

        $this->assertTrue($request->isFromTrustedProxy());
        $this->assertSame('1.2.3.4', $request->clientIp());
    }

    public function test_an_empty_proxy_list_trusts_nothing(): void
    {
        $request = $this->request('127.0.0.1', ['HTTP_X_FORWARDED_HOST' => 'evil.example.com']);
        $request->setTrustedProxies([]);

        $this->assertFalse($request->isFromTrustedProxy());
        $this->assertSame('app.example.com', $request->host());
    }

    // ---------------------------------------------------------------- comma lists & port

    public function test_left_most_entry_wins_in_comma_lists(): void
    {
        $request = $this->request('10.0.0.1', [
            'HTTP_X_FORWARDED_FOR'   => '1.2.3.4, 10.0.0.9',
            'HTTP_X_FORWARDED_HOST'  => 'tenant.example.com, internal.local',
            'HTTP_X_FORWARDED_PROTO' => 'https,http',
        ]);
        $request->setTrustedProxies(['10.0.0.1']);

        $this->assertSame('1.2.3.4', $request->clientIp());
        $this->assertSame('tenant.example.com', $request->host());
        $this->assertSame('https', $request->scheme());
    }

    public function test_port_falls_back_through_host_then_server_port(): void
    {
        $fromHost = $this->request('10.0.0.1', ['HTTP_HOST' => 'app.example.com:8080']);
        $this->assertSame(8080, $fromHost->port());

        $fromServer = $this->request('10.0.0.1', ['HTTP_HOST' => 'app.example.com', 'SERVER_PORT' => '8000']);
        $this->assertSame(8000, $fromServer->port());
    }

    /** A bracketed IPv6 host is full of colons; only a trailing :digits is a port. */
    public function test_ipv6_host_without_a_port_does_not_parse_one_out_of_the_address(): void
    {
        $request = $this->request('10.0.0.1', ['HTTP_HOST' => '[2001:db8::1]', 'SERVER_PORT' => '80']);

        $this->assertSame(80, $request->port());
    }

    public function test_untrusted_forwarded_port_is_ignored(): void
    {
        $request = $this->request('203.0.113.9', [
            'HTTP_X_FORWARDED_PORT' => '8443',
            'SERVER_PORT'           => '80',
        ]);

        $this->assertSame(80, $request->port());
    }
}
