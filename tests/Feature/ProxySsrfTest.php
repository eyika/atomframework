<?php

namespace Eyika\Atom\Framework\Tests\Feature;

use Eyika\Atom\Framework\Http\Proxy;
use Eyika\Atom\Framework\Http\Request;
use Eyika\Atom\Framework\Support\Config;

/**
 * Covers SEC-22: the proxy must block SSRF — no private/reserved/loopback targets,
 * only http(s), and an explicit host allowlist is the trust anchor when configured.
 */
class ProxySsrfTest extends IntegrationTestCase
{
    protected function tearDown(): void
    {
        Config::set('proxy.allowed_hosts', []);
        parent::tearDown();
    }

    private function request(): Request
    {
        return $this->bindRequest('GET', '/');
    }

    public function test_blocks_cloud_metadata_endpoint(): void
    {
        $this->expectException(\Exception::class);
        new Proxy($this->request(), 'http://169.254.169.254/latest/meta-data/');
    }

    public function test_blocks_loopback(): void
    {
        $this->expectException(\Exception::class);
        new Proxy($this->request(), 'http://127.0.0.1/');
    }

    public function test_blocks_private_range(): void
    {
        $this->expectException(\Exception::class);
        new Proxy($this->request(), 'http://10.0.0.5/admin');
    }

    public function test_rejects_non_http_scheme(): void
    {
        $this->expectException(\Exception::class);
        new Proxy($this->request(), 'ftp://example.com/');
    }

    public function test_allowlist_permits_a_listed_host_without_ip_checks(): void
    {
        Config::set('proxy.allowed_hosts', ['api.internal.test']);

        $proxy = new Proxy($this->request(), 'http://api.internal.test/v1');
        $this->assertInstanceOf(Proxy::class, $proxy);
    }
}
