<?php

namespace Eyika\Atom\Framework\Tests\Unit\Http;

use Eyika\Atom\Framework\Http\Request;
use Eyika\Atom\Framework\Support\Url;
use PHPUnit\Framework\TestCase;

/**
 * Covers BUG-47: Request::validateSignature() (and the ValidateSignature middleware)
 * threw NotImplemented. Now implemented as a canonical HMAC (path + sorted query,
 * keyed by app.key) consistent with Support\Url::signedRoute()/validateSignature().
 */
class SignedUrlTest extends TestCase
{
    protected function tearDown(): void
    {
        foreach (['REQUEST_METHOD', 'REQUEST_URI', 'HTTP_HOST'] as $k) {
            unset($_SERVER[$k]);
        }
        $_GET = [];
        parent::tearDown();
    }

    private function key(): string
    {
        return (string) config('app.key');
    }

    private function fabricate(string $path, array $query): Request
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $path . '?' . http_build_query($query);
        $_SERVER['HTTP_HOST'] = 'localhost';
        $_GET = $query;

        return new Request();
    }

    private function sign(string $path, array $query): array
    {
        ksort($query);
        $canonical = $path . (empty($query) ? '' : '?' . http_build_query($query));
        $query['signature'] = hash_hmac('sha256', $canonical, $this->key());

        return $query;
    }

    public function test_valid_signature_passes(): void
    {
        $request = $this->fabricate('/dl/5', $this->sign('/dl/5', ['expires' => time() + 3600]));

        $this->assertTrue($request->hasValidSignature());
    }

    public function test_tampered_signature_fails(): void
    {
        $query = $this->sign('/dl/5', ['expires' => time() + 3600]);
        $query['signature'] = 'tampered';

        $this->assertFalse($this->fabricate('/dl/5', $query)->hasValidSignature());
    }

    public function test_expired_signature_fails(): void
    {
        $request = $this->fabricate('/dl/5', $this->sign('/dl/5', ['expires' => time() - 10]));

        $this->assertFalse($request->hasValidSignature());
    }

    public function test_missing_signature_fails(): void
    {
        $this->assertFalse($this->fabricate('/dl/5', ['foo' => 'bar'])->hasValidSignature());
    }

    public function test_url_validate_signature_roundtrip(): void
    {
        $query = $this->sign('/download/9', ['expires' => time() + 600]);
        $url = '/download/9?' . http_build_query($query);

        $this->assertTrue(Url::validateSignature($url));
        $this->assertFalse(Url::validateSignature('/download/9?expires=' . (time() + 600) . '&signature=bad'));
    }
}
