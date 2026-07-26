<?php

namespace Eyika\Atom\Framework\Tests\Feature;

use Eyika\Atom\Framework\Foundation\Application;
use Eyika\Atom\Framework\Http\JsonResponse;
use Eyika\Atom\Framework\Http\Request;
use Eyika\Atom\Framework\Http\Response;
use Eyika\Atom\Framework\Http\Route;
use Eyika\Atom\Framework\Support\Facade\Facade;
use Eyika\Atom\Framework\Support\Url;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Base for Feature tests: boots a real (fixture) Application and dispatches
 * FABRICATED requests through the full routing/middleware/response pipeline —
 * the framework's Laravel-style built-in integration testing.
 *
 * Requests are fabricated from superglobals ($_SERVER/$_GET/$_POST/$_COOKIE).
 * A JSON request body cannot be injected via php://input under CLI, so JSON-body
 * content tests use unit-level seams until the request source is made injectable
 * (WRK-01).
 */
abstract class IntegrationTestCase extends TestCase
{
    protected Application $app;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resetRouteState();

        // Boot the app in test mode (skips Dotenv; reads the fixture composer.json).
        $this->app = new Application($GLOBALS['base_path'], true);
        Facade::setFacadeApplication($this->app);

        // Minimal facade bindings the HTTP layer resolves during dispatch.
        $this->app->instance('response', new Response());
        $this->app->instance('json_response', new JsonResponse());
    }

    protected function tearDown(): void
    {
        $this->resetRouteState();
        $_GET = $_POST = $_COOKIE = $_FILES = [];
        parent::tearDown();
    }

    /**
     * Register routes for the test. Pass a closure that calls Route::get()/post()/etc.
     */
    protected function withRoutes(callable $register): void
    {
        $register();
    }

    /**
     * Fabricate and dispatch a request through the routing pipeline.
     */
    protected function call(
        string $method,
        string $uri,
        array $query = [],
        array $body = [],
        array $headers = [],
        array $cookies = []
    ): TestResponse {
        $_GET = $query;
        $_POST = $body;
        $_COOKIE = $cookies;
        $_FILES = [];

        $_SERVER['REQUEST_METHOD'] = strtoupper($method);
        $_SERVER['REQUEST_URI'] = $uri;
        $_SERVER['HTTP_HOST'] = $headers['Host'] ?? 'localhost';
        $_SERVER['SERVER_PORT'] = 80;
        foreach ($headers as $key => $value) {
            $_SERVER['HTTP_' . strtoupper(str_replace('-', '_', $key))] = $value;
        }

        $request = new Request();
        $this->app->instance('request', $request);

        // Capture whatever the response echoes; nothing should escape the test.
        ob_start();
        $status = Route::dispatch($request);
        $output = ob_get_clean() ?: '';

        return new TestResponse($output, $status);
    }

    protected function get(string $uri, array $headers = []): TestResponse
    {
        return $this->call('GET', $uri, [], [], $headers);
    }

    protected function post(string $uri, array $body = [], array $headers = []): TestResponse
    {
        return $this->call('POST', $uri, [], $body, $headers);
    }

    /**
     * Clear the static route table + per-request routing state between tests.
     */
    private function resetRouteState(): void
    {
        $defaults = [
            'routes' => [], 'middlewares' => [], 'defaultMiddlewares' => [], 'middlewareAliases' => [],
            'middlewarePriority' => [], 'groupPrefix' => '', 'groupDomains' => [], 'routeName' => '',
            'currentRoute' => '', 'lastInsertedRouteKeys' => '', 'apiRequest' => false, 'lastGroupMiddleware' => [],
        ];
        $ref = new ReflectionClass(Route::class);
        foreach ($defaults as $prop => $value) {
            if ($ref->hasProperty($prop)) {
                $p = $ref->getProperty($prop);
                $p->setAccessible(true);
                $p->setValue(null, $value);
            }
        }

        $urlRef = new ReflectionClass(Url::class);
        if ($urlRef->hasProperty('routes')) {
            $p = $urlRef->getProperty('routes');
            $p->setAccessible(true);
            $p->setValue(null, []);
        }
    }
}
