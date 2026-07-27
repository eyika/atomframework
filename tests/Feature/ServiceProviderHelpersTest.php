<?php

namespace Eyika\Atom\Framework\Tests\Feature;

use Eyika\Atom\Framework\Foundation\ServiceProvider;
use Eyika\Atom\Framework\Http\Route;
use Eyika\Atom\Framework\Support\Config;

/**
 * Exposes the protected PKG-01 package-development helpers for testing.
 */
class PkgTestProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function pubMergeConfig(string $path, string $key): void
    {
        $this->mergeConfigFrom($path, $key);
    }

    public function pubLoadRoutes(string $path): void
    {
        $this->loadRoutesFrom($path);
    }

    public function pubLoadMigrations(string|array $paths): void
    {
        $this->loadMigrationsFrom($paths);
    }

    public function pubLoadViews(string|array $paths, string $ns): void
    {
        $this->loadViewsFrom($paths, $ns);
    }

    public function pubLoadTranslations(string $path, string $ns): void
    {
        $this->loadTranslationsFrom($path, $ns);
    }

    public function pubCommands(array|string $commands): void
    {
        $this->commands($commands);
    }

    public function pubPublishes(array $paths, string $tag = 'default'): void
    {
        $this->publishes($paths, $tag);
    }
}

/**
 * Covers PKG-01: ServiceProvider package helpers — mergeConfigFrom (app overrides
 * package defaults), loadRoutesFrom, and the loadMigrationsFrom/loadViewsFrom/
 * loadTranslationsFrom/commands registries (dedup + accessors).
 */
class ServiceProviderHelpersTest extends IntegrationTestCase
{
    private array $tmpFiles = [];

    protected function setUp(): void
    {
        parent::setUp();
        ServiceProvider::flushPackageRegistrations();
    }

    protected function tearDown(): void
    {
        ServiceProvider::flushPackageRegistrations();
        foreach ($this->tmpFiles as $f) {
            @unlink($f);
        }
        parent::tearDown();
    }

    private function provider(): PkgTestProvider
    {
        return new PkgTestProvider($this->app);
    }

    private function tmp(string $contents): string
    {
        $path = sys_get_temp_dir() . '/atomtest_pkg_' . uniqid('', true) . '.php';
        file_put_contents($path, $contents);
        $this->tmpFiles[] = $path;
        return $path;
    }

    public function test_merge_config_lets_app_override_package_defaults(): void
    {
        $pkgConfig = $this->tmp("<?php return ['driver' => 'pkg', 'ttl' => 60];");

        // Simulate an app override already present for this key.
        Config::set('pkgcfg', ['ttl' => 999]);

        $this->provider()->pubMergeConfig($pkgConfig, 'pkgcfg');

        $this->assertSame('pkg', config('pkgcfg.driver')); // package default added
        $this->assertSame(999, config('pkgcfg.ttl'));       // app value wins
    }

    public function test_merge_config_uses_package_defaults_when_app_has_none(): void
    {
        $pkgConfig = $this->tmp("<?php return ['level' => 'debug'];");

        $this->provider()->pubMergeConfig($pkgConfig, 'freshpkgcfg');

        $this->assertSame('debug', config('freshpkgcfg.level'));
    }

    public function test_load_routes_from_registers_package_routes(): void
    {
        $routes = $this->tmp(<<<'PHP'
<?php
use Eyika\Atom\Framework\Http\Route;
Route::get('/pkg-route', ['App\Controllers\Pkg', 'index']);
PHP);

        $this->provider()->pubLoadRoutes($routes);

        $this->assertArrayHasKey('/pkg-route', Route::getRoutes()['GET']);
    }

    public function test_load_migrations_from_registers_and_dedupes(): void
    {
        $p = $this->provider();
        $p->pubLoadMigrations('/pkg/database/migrations');
        $p->pubLoadMigrations(['/pkg/database/migrations', '/other/migrations']);

        $this->assertSame(
            ['/pkg/database/migrations', '/other/migrations'],
            ServiceProvider::migrationPaths()
        );
    }

    public function test_view_and_translation_namespaces_are_recorded(): void
    {
        $p = $this->provider();
        $p->pubLoadViews('/pkg/resources/views', 'pkg');
        $p->pubLoadTranslations('/pkg/lang', 'pkg');

        $this->assertSame(['pkg' => ['/pkg/resources/views']], ServiceProvider::viewNamespaces());
        $this->assertSame(['pkg' => '/pkg/lang'], ServiceProvider::translationNamespaces());
    }

    public function test_commands_are_registered_and_deduped(): void
    {
        $p = $this->provider();
        $p->pubCommands(['Pkg\\Console\\FooCommand']);
        $p->pubCommands('Pkg\\Console\\FooCommand'); // duplicate + string form
        $p->pubCommands(['Pkg\\Console\\BarCommand']);

        $this->assertSame(
            ['Pkg\\Console\\FooCommand', 'Pkg\\Console\\BarCommand'],
            ServiceProvider::packageCommands()
        );
    }

    public function test_publishes_accumulate_and_filter_by_tag(): void
    {
        // PKG-05: previously tag filtering never worked (list-push vs map-read, plus
        // Arrayable's no-op array access).
        $p = $this->provider();
        $p->pubPublishes(['/pkg/config/a.php' => '/app/config/a.php'], 'config');
        $p->pubPublishes(['/pkg/config/b.php' => '/app/config/b.php'], 'config'); // accumulate
        $p->pubPublishes(['/pkg/views' => '/app/views'], 'views');

        $this->assertSame([
            'config' => ['/pkg/config/a.php' => '/app/config/a.php', '/pkg/config/b.php' => '/app/config/b.php'],
            'views' => ['/pkg/views' => '/app/views'],
        ], $p->getPublishables()->toArray());

        $this->assertSame(
            ['config' => ['/pkg/config/a.php' => '/app/config/a.php', '/pkg/config/b.php' => '/app/config/b.php']],
            $p->getPublishables('config')->toArray()
        );

        $this->assertSame([], $p->getPublishables('nonexistent')->toArray());
    }
}
