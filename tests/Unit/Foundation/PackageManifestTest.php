<?php

namespace Eyika\Atom\Framework\Tests\Unit\Foundation;

use Eyika\Atom\Framework\Foundation\PackageManifest;
use PHPUnit\Framework\TestCase;

/**
 * Covers PKG-02: PackageManifest discovers providers/aliases from installed packages'
 * extra.atom block, prefers the cached manifest, and writeManifest() persists it.
 */
class PackageManifestTest extends TestCase
{
    private string $base;

    protected function setUp(): void
    {
        parent::setUp();
        $this->base = sys_get_temp_dir() . '/atomtest_pkgman_' . uniqid('', true);
        mkdir($this->base . '/vendor/composer', 0775, true);
    }

    protected function tearDown(): void
    {
        // Best-effort recursive cleanup.
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

    private function writeInstalled(array $packages): void
    {
        file_put_contents(
            $this->base . '/vendor/composer/installed.json',
            json_encode(['packages' => $packages])
        );
    }

    public function test_discovers_providers_and_aliases_from_extra_atom(): void
    {
        $this->writeInstalled([
            ['name' => 'acme/widget', 'extra' => ['atom' => [
                'providers' => ['Acme\\Widget\\WidgetServiceProvider'],
                'aliases' => ['Widget' => 'Acme\\Widget\\Facades\\Widget'],
            ]]],
            ['name' => 'acme/plain'], // no extra — ignored
            ['name' => 'acme/tools', 'extra' => ['atom' => [
                'providers' => ['Acme\\Tools\\ToolsServiceProvider'],
            ]]],
        ]);

        $manifest = new PackageManifest($this->base);

        $this->assertSame(
            ['Acme\\Widget\\WidgetServiceProvider', 'Acme\\Tools\\ToolsServiceProvider'],
            $manifest->providers()
        );
        $this->assertSame(['Widget' => 'Acme\\Widget\\Facades\\Widget'], $manifest->aliases());
    }

    public function test_empty_when_no_installed_json(): void
    {
        @unlink($this->base . '/vendor/composer/installed.json');
        $manifest = new PackageManifest($this->base);

        $this->assertSame([], $manifest->providers());
        $this->assertSame([], $manifest->getManifest());
    }

    public function test_write_and_reload_from_cache(): void
    {
        $this->writeInstalled([
            ['name' => 'acme/widget', 'extra' => ['atom' => ['providers' => ['Acme\\Widget\\P']]]],
        ]);

        $manifest = new PackageManifest($this->base);
        $manifest->writeManifest();
        $this->assertFileExists($manifest->manifestPath());

        // A fresh instance must read the cached manifest even if installed.json is
        // then emptied — proving the cache is preferred.
        $this->writeInstalled([]);
        $fresh = new PackageManifest($this->base);
        $this->assertSame(['Acme\\Widget\\P'], $fresh->providers());

        $fresh->deleteManifest();
        $this->assertFileDoesNotExist($manifest->manifestPath());
    }
}
