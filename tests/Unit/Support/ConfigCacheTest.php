<?php

namespace Eyika\Atom\Framework\Tests\Unit\Support;

use Eyika\Atom\Framework\Support\Config;
use PHPUnit\Framework\TestCase;

/**
 * Covers PERF-10: config can be compiled to a single cache file that boots load in
 * one require (skipping the per-file glob), and clearCache() removes it.
 */
class ConfigCacheTest extends TestCase
{
    private function cachePath(): string
    {
        return base_path('bootstrap/cache/config.php');
    }

    protected function setUp(): void
    {
        parent::setUp();
        Config::clearCache();
    }

    protected function tearDown(): void
    {
        Config::clearCache();
        @rmdir(dirname($this->cachePath()));
        parent::tearDown();
    }

    public function test_cache_writes_a_returnable_php_file(): void
    {
        $path = Config::cache();

        $this->assertFileExists($path);
        $cached = require $path;
        $this->assertIsArray($cached);
        // The fixture config/app.php has name 'AtomFixture'.
        $this->assertSame('AtomFixture', $cached['app']['name'] ?? null);
    }

    public function test_boot_reads_from_the_cache_when_present(): void
    {
        // Seed a cache with a value that does NOT exist on disk, then force a fresh
        // singleton — the loaded config must come from the cache.
        Config::cache();
        $path = $this->cachePath();
        $data = require $path;
        $data['app']['name'] = 'FROM_CACHE';
        file_put_contents($path, "<?php\n\nreturn " . var_export($data, true) . ";\n");

        Config::clearCache();               // drops the singleton (and the file)...
        file_put_contents($path, "<?php\n\nreturn " . var_export($data, true) . ";\n"); // ...rewrite it

        $this->assertSame('FROM_CACHE', config('app.name'));
    }

    public function test_clear_cache_removes_the_file(): void
    {
        $path = Config::cache();
        $this->assertFileExists($path);

        Config::clearCache();

        $this->assertFileDoesNotExist($path);
    }
}
