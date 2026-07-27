<?php

namespace Eyika\Atom\Framework\Tests\Unit\Foundation;

use Eyika\Atom\Framework\Foundation\Preloader;
use PHPUnit\Framework\TestCase;

/**
 * Covers PERF-15: the Preloader discovers the PHP files it would compile into OPcache
 * (respecting ignore patterns) and load() is a safe no-op when OPcache is unavailable.
 */
class PreloaderTest extends TestCase
{
    private string $base;

    protected function setUp(): void
    {
        parent::setUp();
        $this->base = sys_get_temp_dir() . '/atomtest_preload_' . uniqid('', true);
        mkdir($this->base . '/sub', 0775, true);
        mkdir($this->base . '/tests', 0775, true);
        file_put_contents($this->base . '/A.php', "<?php class A {}");
        file_put_contents($this->base . '/B.php', "<?php class B {}");
        file_put_contents($this->base . '/sub/C.php', "<?php class C {}");
        file_put_contents($this->base . '/notphp.txt', 'nope');
        file_put_contents($this->base . '/tests/SkipMe.php', "<?php class SkipMe {}");
    }

    protected function tearDown(): void
    {
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->base, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $f) {
            $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        @rmdir($this->base);
        parent::tearDown();
    }

    public function test_discovers_php_files_respecting_ignores(): void
    {
        $files = (new Preloader())
            ->paths([$this->base])
            ->ignore(['/tests/'])
            ->files();

        $names = array_map('basename', $files);
        sort($names);

        $this->assertSame(['A.php', 'B.php', 'C.php'], $names); // recursive, non-php + ignored excluded
    }

    public function test_load_is_a_safe_noop_without_opcache(): void
    {
        // Under CLI phpunit OPcache is typically disabled — load() must not fatal.
        $compiled = (new Preloader())->paths([$this->base])->load();

        $this->assertIsInt($compiled);
        $this->assertGreaterThanOrEqual(0, $compiled);
    }
}
