<?php

namespace Eyika\Atom\Framework\Tests\Feature;

use Eyika\Atom\Framework\Http\Request;
use Eyika\Atom\Framework\Http\Route;

/**
 * Covers the RouteServiceProvider-driven routing: Route::map() registers request-
 * matched route maps and Route::resolveMapFor() picks the first whose matcher accepts
 * the request (matcher-less map = fallback), replacing the Server's hardcoded web/api
 * decision. No maps → null (Server uses its legacy heuristic).
 */
class RouteMapTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Route::flushMaps();
    }

    protected function tearDown(): void
    {
        Route::flushMaps();
        parent::tearDown();
    }

    public function test_matching_map_wins_and_fallback_handles_the_rest(): void
    {
        Route::map('api')->middleware('api')->stateless()
            ->when(fn (Request $r) => $r->wantsJson())
            ->load('/routes/api.php');
        Route::map('web')->middleware('web')
            ->load('/routes/web.php');

        $jsonReq = $this->bindRequest('GET', '/anything', [], ['Accept' => 'application/json']);
        $apiMap = Route::resolveMapFor($jsonReq);
        $this->assertSame('api', $apiMap->getName());
        $this->assertTrue($apiMap->isStateless());
        $this->assertSame('api', $apiMap->getMiddleware());
        $this->assertSame('/routes/api.php', $apiMap->getFile());

        $webReq = $this->bindRequest('GET', '/dashboard', [], []);
        $webMap = Route::resolveMapFor($webReq);
        $this->assertSame('web', $webMap->getName());
        $this->assertFalse($webMap->isStateless());
    }

    public function test_no_maps_returns_null(): void
    {
        $req = $this->bindRequest('GET', '/x');
        $this->assertNull(Route::resolveMapFor($req));
    }

    public function test_first_matching_map_wins_by_registration_order(): void
    {
        Route::map('admin')
            ->when(fn (Request $r) => str_starts_with(ltrim($r->pathInfo(), '/'), 'admin'))
            ->load('/routes/admin.php');
        Route::map('catchall')
            ->when(fn (Request $r) => true) // would also match, but registered second
            ->load('/routes/catchall.php');

        $req = $this->bindRequest('GET', '/admin/users');
        $this->assertSame('admin', Route::resolveMapFor($req)->getName());
    }
}
