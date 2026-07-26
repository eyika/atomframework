<?php

namespace Eyika\Atom\Framework\Tests\Feature;

use Eyika\Atom\Framework\Http\Route;

/**
 * Smoke test for the integration harness itself: a fabricated request must flow
 * through Route::dispatch -> middleware pipeline -> callback -> Response::send and
 * come back with the expected body.
 */
class RoutingSmokeTest extends IntegrationTestCase
{
    public function test_get_route_returns_body(): void
    {
        $this->withRoutes(function () {
            Route::get('/ping', fn($request) => 'pong');
        });

        $this->get('/ping')->assertBodyContains('pong');
    }

    public function test_route_parameter_is_bound_and_passed_to_callback(): void
    {
        $this->withRoutes(function () {
            Route::get('/hello/{name}', fn($request, $name) => "hi $name");
        });

        $this->get('/hello/world')->assertBodyContains('hi world');
    }

    public function test_query_string_is_parsed_from_fabricated_request(): void
    {
        $this->withRoutes(function () {
            Route::get('/echo', fn($request) => 'q=' . $request->query('q'));
        });

        $this->call('GET', '/echo', ['q' => 'abc'])->assertBodyContains('q=abc');
    }
}
