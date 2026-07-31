<?php

namespace Eyika\Atom\Framework\Tests\Unit\Auth;

use Eyika\Atom\Framework\Support\Auth\Drivers\EloquentDriver;
use Eyika\Atom\Framework\Support\Config;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Regression: EloquentDriver must authenticate against the GUARD PROVIDER's model
 * (`auth.providers.<provider>.model`), not always the global `auth.user.model`. Before the fix the
 * driver discarded the provider name and hardcoded the global model in validateCredentials /
 * getUserById / getUserByColumn, so a second guard/provider (e.g. a storefront Customer alongside a
 * staff User) silently resolved the wrong model — a cross-model auth bug. It must still fall back to
 * the global model for legacy single-guard apps.
 */
class EloquentDriverProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Config::set('auth.user.model', 'App\\Models\\User');   // legacy global
        Config::set('auth.providers', [
            'users'     => ['driver' => 'eloquent'],            // no explicit model → global fallback
            'customers' => ['driver' => 'eloquent', 'model' => 'App\\Models\\Customer'],
        ]);
    }

    /** Resolve the (protected) modelClass() the driver's read methods all delegate to. */
    private function modelClassFor(string $provider): string
    {
        $driver = new EloquentDriver($provider);
        $m = new ReflectionMethod($driver, 'modelClass');
        $m->setAccessible(true);
        return $m->invoke($driver);
    }

    public function test_provider_with_model_resolves_that_model(): void
    {
        $this->assertSame('App\\Models\\Customer', $this->modelClassFor('customers'));
    }

    public function test_provider_without_model_falls_back_to_global(): void
    {
        $this->assertSame('App\\Models\\User', $this->modelClassFor('users'));
    }

    public function test_two_providers_resolve_distinct_models(): void
    {
        // The crux of the bug: two guards/providers must NOT collapse to the same model.
        $this->assertNotSame($this->modelClassFor('users'), $this->modelClassFor('customers'));
    }
}
