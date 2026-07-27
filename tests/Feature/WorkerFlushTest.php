<?php

namespace Eyika\Atom\Framework\Tests\Feature;

use Eyika\Atom\Framework\Http\Route;
use Eyika\Atom\Framework\Support\Auth\Auth;
use ReflectionProperty;

/**
 * Covers the worker request-lifecycle reset (WRK-03/04/05/09): Application::
 * flushRequestState() clears identity, routing, facade-resolved instances, and
 * request-scoped bindings so a persistent worker doesn't leak one request into the
 * next — while app-level singletons persist.
 */
class WorkerFlushTest extends IntegrationTestCase
{
    protected function tearDown(): void
    {
        Auth::flush();
        parent::tearDown();
    }

    public function test_flush_request_state_clears_scoped_identity_and_routes(): void
    {
        // A request-scoped binding + an app-level singleton (should survive).
        $this->app->scoped('req.scoped', fn () => (object) ['id' => uniqid()]);
        $this->app->instance('app.singleton', 'PERSISTENT');
        $first = $this->app->make('req.scoped');

        // Residual auth identity + a registered route (simulating a served request).
        $jwt = new ReflectionProperty(Auth::class, 'jwt');
        $jwt->setAccessible(true);
        $jwt->setValue(null, 'leaked-token');
        Route::get('/leak', fn () => 'x');
        $this->assertArrayHasKey('GET', Route::getRoutes());

        $this->app->flushRequestState();

        // Scoped binding re-resolves (new instance).
        $this->assertNotSame($first, $this->app->make('req.scoped'));
        // Identity cleared.
        $this->assertSame('', Auth::getJwt());
        // Route table cleared.
        $this->assertSame([], Route::getRoutes());
        // App-level singleton preserved.
        $this->assertSame('PERSISTENT', $this->app->make('app.singleton'));
    }
}
