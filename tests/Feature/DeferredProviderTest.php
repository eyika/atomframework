<?php

namespace Eyika\Atom\Framework\Tests\Feature;

use Eyika\Atom\Framework\Foundation\Contracts\DeferrableProvider;
use Eyika\Atom\Framework\Foundation\ServiceProvider;
use Eyika\Atom\Framework\Support\Config;

class DeferredProbeProvider extends ServiceProvider implements DeferrableProvider
{
    public static int $registerCount = 0;

    public function register(): void
    {
        self::$registerCount++;
        $this->app->instance('probe.svc', 'PROBE_VALUE');
    }

    public function provides(): array
    {
        return ['probe.svc'];
    }
}

/**
 * Covers PKG-04: a DeferrableProvider is not registered/booted during
 * registerProviders(); it registers on the FIRST make() of a service it provides,
 * and only once.
 */
class DeferredProviderTest extends IntegrationTestCase
{
    protected function tearDown(): void
    {
        DeferredProbeProvider::$registerCount = 0;
        Config::clearCache(); // restore fixture config for other tests
        parent::tearDown();
    }

    public function test_deferred_provider_registers_only_on_first_resolution(): void
    {
        DeferredProbeProvider::$registerCount = 0;
        Config::set('app.providers', [DeferredProbeProvider::class]);

        $this->app->registerProviders();

        // Deferred: register() must not have run yet.
        $this->assertSame(0, DeferredProbeProvider::$registerCount);

        // First resolution triggers registration on demand.
        $this->assertSame('PROBE_VALUE', $this->app->make('probe.svc'));
        $this->assertSame(1, DeferredProbeProvider::$registerCount);

        // Subsequent resolutions reuse the binding — no re-registration.
        $this->app->make('probe.svc');
        $this->assertSame(1, DeferredProbeProvider::$registerCount);
    }
}
