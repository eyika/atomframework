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

    /**
     * A link INSIDE the configured base, pointing at storage elsewhere — the shape `storage:link`
     * creates, and the one a media volume mounted outside the app also takes.
     *
     * Confinement used to be decided with `realpath()`, which collapses `..` AND resolves symlinks
     * in one step, so the file behind such a link resolved outside the base and was refused. That
     * is the same conflation that made ServePublicAssets 404 every upload under `public/storage`.
     * Traversal is lexical; following a link the operator placed there is not.
     */
    public function test_a_file_behind_a_link_inside_the_base_is_served(): void
    {
        $root   = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'dllink_' . uniqid();
        $base   = $root . DIRECTORY_SEPARATOR . 'downloads';
        $target = $root . DIRECTORY_SEPARATOR . 'media';
        mkdir($base, 0777, true);
        mkdir($target, 0777, true);
        file_put_contents($target . DIRECTORY_SEPARATOR . 'invoice.pdf', 'PDFBYTES');

        $link = $base . DIRECTORY_SEPARATOR . 'media';
        if (!$this->makeLink($target, $link)) {
            $this->fail('could not create a link; the regression cannot be exercised');
        }

        Config::set('filesystem.download_base', $base);

        $response = (new Response())->download($link . DIRECTORY_SEPARATOR . 'invoice.pdf');

        $this->assertTrue((bool) $this->prop($response, 'isFileResponse'), 'a linked file inside the base was refused');

        @rmdir($link);
        @unlink($link);
        @unlink($target . DIRECTORY_SEPARATOR . 'invoice.pdf');
        @rmdir($target);
        @rmdir($base);
        @rmdir($root);
    }

    /** A climb out of the base is still refused — lexically, before any link is resolved. */
    public function test_a_climb_out_of_the_base_is_still_refused(): void
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'dlclimb_' . uniqid();
        $base = $root . DIRECTORY_SEPARATOR . 'downloads';
        mkdir($base, 0777, true);
        file_put_contents($root . DIRECTORY_SEPARATOR . 'secret.txt', 'secret');

        Config::set('filesystem.download_base', $base);

        $climb = $base . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'secret.txt';
        $this->assertSame('File not found.', $this->prop((new Response())->download($climb), 'body'));

        @unlink($root . DIRECTORY_SEPARATOR . 'secret.txt');
        @rmdir($base);
        @rmdir($root);
    }

    /**
     * `symlink()` needs elevation on Windows; a junction does not, and `realpath()` follows both
     * identically. Falling back keeps this from silently skipping on the platform it was found on.
     */
    private function makeLink(string $target, string $link): bool
    {
        if (@symlink($target, $link)) {
            return true;
        }

        if (DIRECTORY_SEPARATOR !== '\\') {
            return false;
        }

        @exec(sprintf(
            'cmd /c mklink /J %s %s 2>&1',
            escapeshellarg(str_replace('/', '\\', $link)),
            escapeshellarg(str_replace('/', '\\', $target))
        ), $output, $status);

        return $status === 0;
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
