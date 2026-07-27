<?php

namespace Eyika\Atom\Framework\Tests\Unit\Http;

use Eyika\Atom\Framework\Http\Response;
use Eyika\Atom\Framework\Support\Config;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * Covers SEC-28: download() resolves realpath, optionally confines to a base dir,
 * and sanitises the Content-Disposition filename (no header injection).
 */
class DownloadTest extends TestCase
{
    protected function tearDown(): void
    {
        Config::set('filesystem.download_base', null);
        parent::tearDown();
    }

    private function prop(Response $response, string $name): mixed
    {
        $p = new ReflectionProperty($response, $name);
        $p->setAccessible(true);
        return $p->getValue($response);
    }

    private function header(Response $response, string $key): ?string
    {
        foreach ((array) $this->prop($response, 'headers') as $entry) {
            foreach ($entry as $k => $v) {
                if ($k === $key) {
                    return $v[0];
                }
            }
        }
        return null;
    }

    public function test_nonexistent_file_is_not_found(): void
    {
        $response = (new Response())->download('/definitely/not/here.txt');
        $this->assertSame('File not found.', $this->prop($response, 'body'));
        $this->assertFalse((bool) $this->prop($response, 'isFileResponse'));
    }

    public function test_real_file_served_with_resolved_realpath(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'dl');
        file_put_contents($tmp, 'hello');

        $response = (new Response())->download($tmp);
        $this->assertTrue((bool) $this->prop($response, 'isFileResponse'));
        $this->assertSame(realpath($tmp), $this->prop($response, 'file_path'));

        unlink($tmp);
    }

    public function test_file_outside_configured_base_is_blocked(): void
    {
        $base = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'dlbase_' . uniqid();
        mkdir($base);
        $inside = $base . DIRECTORY_SEPARATOR . 'ok.txt';
        file_put_contents($inside, 'ok');
        $outside = tempnam(sys_get_temp_dir(), 'out');
        file_put_contents($outside, 'secret');

        Config::set('filesystem.download_base', $base);

        $this->assertSame('File not found.', $this->prop((new Response())->download($outside), 'body'));
        $this->assertTrue((bool) $this->prop((new Response())->download($inside), 'isFileResponse'));

        unlink($inside);
        unlink($outside);
        rmdir($base);
    }

    public function test_download_filename_has_no_crlf(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'dl');
        file_put_contents($tmp, 'x');

        $response = (new Response())->download($tmp, "evil\r\nSet-Cookie: a=1\".txt");
        $disposition = $this->header($response, 'Content-Disposition');

        $this->assertNotNull($disposition);
        $this->assertStringNotContainsString("\r", $disposition);
        $this->assertStringNotContainsString("\n", $disposition);
        // Exactly one pair of wrapping quotes — the payload's embedded quote is gone.
        $this->assertSame(2, substr_count($disposition, '"'));

        unlink($tmp);
    }
}
