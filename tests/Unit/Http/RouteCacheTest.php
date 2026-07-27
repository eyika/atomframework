<?php

namespace Eyika\Atom\Framework\Tests\Unit\Http;

use Eyika\Atom\Framework\Http\Route;
use PHPUnit\Framework\TestCase;

/**
 * Covers PERF-11: closure-free route files compile to a cache that boots load in one
 * require; files with closure routes are detected as uncacheable (their closures stay
 * dynamic). loadRoutesFile prefers the cache and falls back to the source.
 */
class RouteCacheTest extends TestCase
{
    private array $tmpFiles = [];

    protected function setUp(): void
    {
        parent::setUp();
        Route::clearRegistered();
    }

    protected function tearDown(): void
    {
        Route::clearRegistered();
        foreach ($this->tmpFiles as $f) {
            @unlink($f);
        }
        foreach (['testx', 'testy'] as $name) {
            $p = Route::routeCachePath($name);
            if ($p && is_file($p)) {
                @unlink($p);
            }
        }
        parent::tearDown();
    }

    private function tmp(string $contents): string
    {
        // uniqid() (not pid+count) so a path is never reused across tests — a reused
        // path already pulled in by require/require_once elsewhere would be skipped.
        $path = sys_get_temp_dir() . '/atomtest_routes_' . uniqid('', true) . '.php';
        file_put_contents($path, $contents);
        $this->tmpFiles[] = $path;
        return $path;
    }

    public function test_build_data_for_closure_free_file(): void
    {
        $source = $this->tmp(<<<'PHP'
<?php
use Eyika\Atom\Framework\Http\Route;
Route::get('/alpha', ['App\Controllers\Thing', 'index']);
Route::get('/beta', ['App\Controllers\Thing', 'show']);
PHP);

        $data = Route::buildRouteCacheData($source);

        $this->assertSame([], $data['closures']);
        $this->assertArrayHasKey('/alpha', $data['routes']['GET']);
        $this->assertArrayHasKey('/beta', $data['routes']['GET']);
        // A closure-free table must be var_export-able.
        $this->assertIsString(var_export($data['routes'], true));
    }

    public function test_build_data_detects_closure_routes(): void
    {
        $source = $this->tmp(<<<'PHP'
<?php
use Eyika\Atom\Framework\Http\Route;
Route::get('/ok', ['App\Controllers\Thing', 'index']);
Route::get('/inline', function () { return 'hi'; });
PHP);

        $data = Route::buildRouteCacheData($source);

        $this->assertNotEmpty($data['closures']);
        $this->assertContains('GET /inline', $data['closures']);
    }

    public function test_load_prefers_cache_over_source(): void
    {
        $cachePath = Route::routeCachePath('testx');
        @mkdir(dirname($cachePath), 0775, true);
        file_put_contents($cachePath, "<?php\nreturn " . var_export([
            'GET' => ['/cached' => ['callback' => ['C', 'm'], 'middlewares' => [], 'name' => '/cached', 'domains' => []]],
        ], true) . ";\n");

        // Source path intentionally does not exist — the cache must win.
        Route::loadRoutesFile('testx', '/no/such/source.php');

        $this->assertArrayHasKey('/cached', Route::getRoutes()['GET']);
    }

    public function test_load_falls_back_to_source_when_no_cache(): void
    {
        // Ensure no cache for this name.
        $p = Route::routeCachePath('testy');
        if ($p && is_file($p)) {
            @unlink($p);
        }

        $source = $this->tmp(<<<'PHP'
<?php
use Eyika\Atom\Framework\Http\Route;
Route::get('/fromsource', ['App\Controllers\Thing', 'index']);
PHP);

        Route::loadRoutesFile('testy', $source);

        $this->assertArrayHasKey('/fromsource', Route::getRoutes()['GET']);
    }
}
