<?php

namespace Eyika\Atom\Framework\Tests\Unit\Support;

use Eyika\Atom\Framework\Support\Path;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Reported by a downstream consumer: every file under `public/storage` 404'd when PHP served the
 * assets itself.
 *
 * The asset middleware confined requests with `realpath()`, which collapses `..` **and** resolves
 * symlinks in one step. `public/storage` is a symlink — created by this framework's own
 * `storage:link` — so a legitimate upload resolved outside the webroot and was refused. Apache and
 * LiteSpeed follow symlinks by default, so production was fine and only `artisan serve` broke,
 * silently: the page rendered around the gap and nothing was logged.
 *
 * The distinction these helpers exist to draw: **traversal is lexical** (`..` climbing out of a
 * root), **following a symlink is a deployment decision**. `realpath()` conflates them, so a guard
 * built on it cannot refuse the first without forbidding the second.
 */
class PathTest extends TestCase
{
    /** Build an expected filesystem path with this platform's separator. */
    private function fs(string $path): string
    {
        return str_replace('/', DIRECTORY_SEPARATOR, $path);
    }

    // ---------------------------------------------------------------- request paths

    #[DataProvider('uriProvider')]
    public function test_request_paths_normalise_lexically(string $uri, ?string $expected): void
    {
        $this->assertSame($expected, Path::normalizeUri($uri));
    }

    public static function uriProvider(): array
    {
        return [
            'plain'                     => ['/img/logo.png', '/img/logo.png'],
            'through a symlinked dir'   => ['/storage/products/2/shot.jpg', '/storage/products/2/shot.jpg'],
            'single dot'                => ['/img/./logo.png', '/img/logo.png'],
            'inner climb stays inside'  => ['/img/sub/../logo.png', '/img/logo.png'],
            'double slash'              => ['/img//logo.png', '/img/logo.png'],
            'trailing climb'            => ['/img/sub/..', '/img'],
            'root'                      => ['/', '/'],
            'empty'                     => ['', '/'],

            // every one of these must be refused BEFORE any filesystem call
            'climbs out'                => ['/../secrets.json', null],
            'climbs out twice'          => ['/../../secrets.json', null],
            'climbs out mid-path'       => ['/img/../../secrets.json', null],
            'dotfile above root'        => ['/../.env', null],
            'backslash climb'           => ['\\..\\..\\secrets.json', null],
            'mixed separators'          => ['/img/..\\../secrets.json', null],
        ];
    }

    /**
     * Percent-encoding is NOT decoded here, so `%2e%2e` stays an opaque segment rather than
     * becoming `..`. That is safe — it names a directory that does not exist, so the request 404s —
     * and it is deliberately left alone rather than decoded as part of a security fix.
     */
    public function test_percent_encoded_traversal_is_not_silently_decoded(): void
    {
        $this->assertSame('/%2e%2e/secrets.json', Path::normalizeUri('/%2e%2e/secrets.json'));
    }

    // ---------------------------------------------------------------- filesystem paths

    public function test_filesystem_paths_collapse_without_touching_disk(): void
    {
        $this->assertSame($this->fs('/var/www/storage/f.jpg'), Path::normalize('/var/www/public/../storage/f.jpg'));
        $this->assertSame($this->fs('/var/www/public/f.jpg'), Path::normalize('/var/www/./public/f.jpg'));
        $this->assertSame($this->fs('/a/b'), Path::normalize('/a/b/c/..'));
    }

    /** It must work on a path that does not exist — that is what separates it from realpath(). */
    public function test_normalising_does_not_require_the_path_to_exist(): void
    {
        $this->assertSame(
            $this->fs('/definitely/not/here.jpg'),
            Path::normalize('/definitely/absent/../not/here.jpg')
        );
    }

    public function test_a_windows_drive_is_preserved(): void
    {
        $this->assertSame('C:' . $this->fs('/app/storage/f.jpg'), Path::normalize('C:/app/public/../storage/f.jpg'));
    }

    // ---------------------------------------------------------------- containment

    public function test_a_path_inside_the_root_is_contained(): void
    {
        $this->assertTrue(Path::isWithin('/app/public/img/logo.png', '/app/public'));
        $this->assertTrue(Path::isWithin('/app/public/storage/products/shot.jpg', '/app/public'));
    }

    public function test_a_path_outside_the_root_is_not_contained(): void
    {
        $this->assertFalse(Path::isWithin('/app/storage/f.jpg', '/app/public'));
        $this->assertFalse(Path::isWithin('/etc/passwd', '/app/public'));
    }

    /** The whole point: `..` is refused lexically, with no filesystem involved. */
    public function test_a_climb_out_of_the_root_is_not_contained(): void
    {
        $this->assertFalse(Path::isWithin('/app/public/../secrets.json', '/app/public'));
        $this->assertFalse(Path::isWithin('/app/public/img/../../../etc/passwd', '/app/public'));
    }

    /** A sibling whose name merely STARTS with the root must not pass a prefix test. */
    public function test_a_sibling_with_a_shared_prefix_is_not_contained(): void
    {
        $this->assertFalse(Path::isWithin('/app/public-backup/secrets.json', '/app/public'));
        $this->assertFalse(Path::isWithin('/app/publicX/f.jpg', '/app/public'));
    }

    /** The root itself is not "within" the root — only things under it are. */
    public function test_the_root_itself_is_not_contained(): void
    {
        $this->assertFalse(Path::isWithin('/app/public', '/app/public'));
    }

    public function test_a_trailing_separator_on_the_root_is_tolerated(): void
    {
        $this->assertTrue(Path::isWithin('/app/public/img/logo.png', '/app/public/'));
    }

    /**
     * On Windows two spellings of the same directory ARE the same directory, so a case-sensitive
     * prefix test would refuse a legitimate path.
     */
    public function test_comparison_matches_the_platform_case_rules(): void
    {
        $mixedCase = Path::isWithin('/App/Public/img/logo.png', '/app/public');

        DIRECTORY_SEPARATOR === '\\'
            ? $this->assertTrue($mixedCase, 'Windows paths are case-insensitive')
            : $this->assertFalse($mixedCase, 'POSIX paths are case-sensitive');
    }
}
