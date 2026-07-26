<?php

namespace Eyika\Atom\Framework\Tests\Unit\Http;

use Eyika\Atom\Framework\Http\Request;
use Eyika\Atom\Framework\Support\Config;
use PHPUnit\Framework\TestCase;

/**
 * Covers SEC-25: X-Forwarded-For is only trusted behind a configured proxy, and
 * Host-header poisoning falls back to the app host when a trusted-hosts allowlist
 * is set.
 */
class RequestClientTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_X_FORWARDED_FOR'], $_SERVER['HTTP_HOST']);
        Config::set('app.trusted_hosts', []);
        parent::tearDown();
    }

    public function test_client_ip_ignores_forwarded_for_without_trusted_proxy(): void
    {
        $_SERVER['REMOTE_ADDR'] = '203.0.113.9';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '10.1.2.3'; // attacker-supplied

        $this->assertSame('203.0.113.9', (new Request())->clientIp());
    }

    public function test_client_ip_honours_forwarded_for_from_trusted_proxy(): void
    {
        $_SERVER['REMOTE_ADDR'] = '203.0.113.9';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.7, 10.1.2.3';

        $request = new Request();
        $request->setTrustedProxies(['203.0.113.9']);

        $this->assertSame('198.51.100.7', $request->clientIp()); // left-most real client
    }

    public function test_host_returns_http_host_by_default(): void
    {
        $_SERVER['HTTP_HOST'] = 'shop.example.com';

        $this->assertSame('shop.example.com', (new Request())->host());
    }

    public function test_host_falls_back_to_app_host_when_poisoned(): void
    {
        Config::set('app.trusted_hosts', ['shop.example.com']);
        $_SERVER['HTTP_HOST'] = 'evil.attacker.com';

        // Fixture app.url is http://localhost.
        $this->assertSame('localhost', (new Request())->host());
    }
}
