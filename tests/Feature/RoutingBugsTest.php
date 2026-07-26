<?php

namespace Eyika\Atom\Framework\Tests\Feature;

use Eyika\Atom\Framework\Http\Route;

/**
 * Covers BUG-10 (route() name→URL uses {key}), BUG-11 (optional {param?} captured
 * without the "?"), BUG-12 (Route::any() actually dispatches), BUG-13 (extra
 * request segments don't OOB / falsely match).
 */
class RoutingBugsTest extends IntegrationTestCase
{
    public function test_named_route_url_generation(): void
    {
        Route::get('/users/{id}/posts/{slug}', fn() => 'ok');
        Route::name('user.post');

        $this->assertSame('/users/5/posts/hello', Route::route('user.post', ['id' => 5, 'slug' => 'hello']));
    }

    public function test_any_route_is_dispatched(): void
    {
        Route::any('/any-thing', fn() => 'any-ok');

        $this->get('/any-thing')->assertBodyContains('any-ok');
    }

    public function test_optional_parameter_present_and_absent(): void
    {
        Route::get('/opt/{id?}', fn($req) => 'id=' . ($req->route_params['id'] ?? 'none'));

        $this->get('/opt/7')->assertBodyContains('id=7');
        $this->get('/opt')->assertBodyContains('id=none');
    }

    public function test_extra_request_segments_do_not_match(): void
    {
        Route::any('/404', fn() => '404-page');
        Route::get('/single', fn() => 'single');

        // '/single/extra/parts' must not OOB-match '/single' → falls through to 404.
        $this->get('/single/extra/parts')->assertBodyContains('404-page');
    }
}
