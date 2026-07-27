<?php

namespace Eyika\Atom\Framework\Tests\Feature;

use Eyika\Atom\Framework\Http\Route;

/**
 * Covers PERF-12: static routes match via exact string equality (no explode + regex),
 * while dynamic routes still use matchesRoute(). The key guarantee is that
 * FIRST-REGISTERED-WINS precedence is preserved — a static-hash-first shortcut would
 * have silently made static routes beat an earlier-registered dynamic route.
 */
class DispatcherStaticFastPathTest extends IntegrationTestCase
{
    public function test_static_route_matches(): void
    {
        Route::get('/health', fn() => 'ok');

        $this->get('/health')->assertBodyContains('ok');
    }

    public function test_dynamic_route_still_captures_params(): void
    {
        Route::get('/items/{id}', fn($req) => 'id=' . $req->route_params['id']);

        $this->get('/items/42')->assertBodyContains('id=42');
    }

    public function test_dynamic_registered_first_wins_over_later_static(): void
    {
        // Dynamic first: '/x/special' must resolve to the DYNAMIC route (registration
        // order), not the later static one. This is the precedence guarantee.
        Route::get('/x/{slug}', fn($req) => 'dynamic:' . $req->route_params['slug']);
        Route::get('/x/special', fn() => 'static');

        $this->get('/x/special')->assertBodyContains('dynamic:special');
    }

    public function test_static_registered_first_wins_over_later_dynamic(): void
    {
        // Static first: '/y/special' resolves to the STATIC route.
        Route::get('/y/special', fn() => 'static');
        Route::get('/y/{slug}', fn($req) => 'dynamic:' . $req->route_params['slug']);

        $this->get('/y/special')->assertBodyContains('static');
    }
}
