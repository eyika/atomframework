<?php

namespace Eyika\Atom\Framework\Tests\Unit\Support;

use Eyika\Atom\Framework\Foundation\ServiceProvider;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * Covers PKG-09: resolve_view_template() maps a namespaced view ('pkg::name') to the
 * package's registered view directories (loadViewsFrom / PKG-01) plus the app views
 * as fallback; plain and unknown-namespace names pass through to the app path.
 */
class ViewNamespaceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        ServiceProvider::flushPackageRegistrations();
    }

    protected function tearDown(): void
    {
        ServiceProvider::flushPackageRegistrations();
        parent::tearDown();
    }

    public function test_plain_view_uses_app_path(): void
    {
        $this->assertSame(
            ['/app/resources/views', 'home'],
            resolve_view_template('home', '/app/resources/views')
        );
    }

    public function test_registered_namespace_prepends_package_paths(): void
    {
        // Seed the registry directly (loadViewsFrom writes here; two dirs accumulate).
        $prop = new ReflectionProperty(ServiceProvider::class, 'viewNamespaces');
        $prop->setAccessible(true);
        $prop->setValue(null, ['billing' => ['/pkg/a/views', '/pkg/b/views']]);

        [$paths, $view] = resolve_view_template('billing::invoice', '/app/resources/views');

        $this->assertSame(['/pkg/a/views', '/pkg/b/views', '/app/resources/views'], $paths);
        $this->assertSame('invoice', $view);
    }

    public function test_unknown_namespace_passes_through(): void
    {
        // No 'ghost' namespace registered → full name kept for a clear engine error.
        $this->assertSame(
            ['/app/resources/views', 'ghost::x'],
            resolve_view_template('ghost::x', '/app/resources/views')
        );
    }
}
