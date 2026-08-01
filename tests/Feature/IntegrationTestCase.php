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

    /** Facade app in effect before this test — restored in tearDown so it doesn't leak forward. */
    private ?Application $previousFacadeApp = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resetRouteState();

        // Boot the app in test mode (skips Dotenv; reads the fixture composer.json).
        $this->previousFacadeApp = Facade::getFacadeApplication();
        $this->app = new Application($GLOBALS['base_path'], true);
        Facade::setFacadeApplication($this->app);

        // Minimal facade bindings the HTTP layer resolves during dispatch.
        $this->app->instance('response', new Response());
        $this->app->instance('json_response', new JsonResponse());
        $this->app->instance('encrypter', new \Eyika\Atom\Framework\Support\Encrypter());
    }

    protected function tearDown(): void
    {
        $this->resetRouteState();
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($this->previousFacadeApp);
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
     * Bind a lightweight in-memory session double (avoids native $_SESSION / save
     * handlers / DB during unit-style Feature tests).
     */
    protected function bindSession(array $data = []): void
    {
        $session = new class($data) {
            public function __construct(private array $data)
            {
            }
            public function has(string $k): bool
            {
                return array_key_exists($k, $this->data);
            }
            public function get(string $k, mixed $default = null): mixed
            {
                return $this->data[$k] ?? $default;
            }
            public function set(string $k, mixed $v): void
            {
                $this->data[$k] = $v;
            }
            public function forget(string $k): void
            {
                unset($this->data[$k]);
            }
        };

        $this->app->instance('session', $session);
        // The facade caches resolved instances statically (WRK-04) — drop it so the
        // freshly-bound session is used rather than a previous test's.
        Facade::clearResolvedInstances();
    }

    /**
     * Fabricate + bind a Request (without dispatching) for tests that call framework
     * code which resolves the current request via the facade.
     */
    protected function bindRequest(string $method = 'GET', string $uri = '/', array $input = [], array $headers = []): Request
    {
        $_GET = [];
        $_POST = $input;
        $_COOKIE = [];
        $_FILES = [];
        $this->purgeServerHeaders();
        $_SERVER['REQUEST_METHOD'] = strtoupper($method);
        $_SERVER['REQUEST_URI'] = $uri;
        $_SERVER['HTTP_HOST'] = 'localhost';
        foreach ($headers as $key => $value) {
            $_SERVER['HTTP_' . strtoupper(str_replace('-', '_', $key))] = $value;
        }

        $request = new Request();
        $this->app->instance('request', $request);
        // Drop the facade's cached request (WRK-04) so this fabricated one is used.
        Facade::clearResolvedInstances();

        return $request;
    }

    /**
     * Clear stale HTTP_ and CONTENT_ server keys so a header from a previous
     * fabricated request doesn't bleed into the next one.
     */
    private function purgeServerHeaders(): void
    {
        foreach (array_keys($_SERVER) as $key) {
            if (str_starts_with($key, 'HTTP_') || $key === 'CONTENT_TYPE' || $key === 'CONTENT_LENGTH') {
                unset($_SERVER[$key]);
            }
        }
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
        array $cookies = [],
        ?array $json = null
    ): TestResponse {
        $_GET = $query;
        $_POST = $body;
        $_COOKIE = $cookies;
        $_FILES = [];

        $this->purgeServerHeaders();
        $_SERVER['REQUEST_METHOD'] = strtoupper($method);
        $_SERVER['REQUEST_URI'] = $uri;
        $_SERVER['HTTP_HOST'] = $headers['Host'] ?? 'localhost';
        $_SERVER['SERVER_PORT'] = 80;
        foreach ($headers as $key => $value) {
            $_SERVER['HTTP_' . strtoupper(str_replace('-', '_', $key))] = $value;
        }
        if ($json !== null) {
            $_SERVER['CONTENT_TYPE'] = 'application/json';
        }

        // WRK-01: build the request from an injectable source. This is what lets us
        // inject a JSON body — php://input can't be written under the CLI SAPI, which
        // previously blocked JSON-body Feature tests.
        $source = [
            'server'  => $_SERVER,
            'query'   => $query,
            'post'    => $body,
            'cookies' => $cookies,
            'files'   => [],
            'headers' => getallheaders(),
        ];
        if ($json !== null) {
            $source['rawBody'] = json_encode($json);
        }

        $request = new Request($source);
        $this->app->instance('request', $request);
        // Fresh response instances per request: the Response facade is a shared
        // mutable singleton, so a prior request's state (e.g. _responseSent) would
        // otherwise leak into this one.
        $this->app->instance('response', new Response());
        $this->app->instance('json_response', new JsonResponse());
        Facade::clearResolvedInstances();

        // Capture whatever the response echoes; nothing should escape the test.
        ob_start();
        try {
            $status = Route::dispatch($request);
        } finally {
            $output = ob_get_clean() ?: '';
        }

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

    /** POST a JSON body (injected via the request source — WRK-01). */
    protected function postJson(string $uri, array $json = [], array $headers = []): TestResponse
    {
        return $this->call('POST', $uri, [], [], $headers, [], $json);
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
