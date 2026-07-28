<?php

namespace Eyika\Atom\Framework\Support\Testing;

use Eyika\Atom\Framework\Foundation\Application;
use Eyika\Atom\Framework\Foundation\Contracts\ExceptionHandler;
use Eyika\Atom\Framework\Foundation\Contracts\Kernel;
use Eyika\Atom\Framework\Http\BaseResponse;
use Eyika\Atom\Framework\Http\JsonResponse;
use Eyika\Atom\Framework\Http\Request;
use Eyika\Atom\Framework\Http\Response;
use Eyika\Atom\Framework\Http\Route;
use Eyika\Atom\Framework\Http\Server;
use Eyika\Atom\Framework\Support\Facade\Facade;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use Throwable;

/**
 * Base class for application feature/integration tests. It boots your real application
 * once (providers, RouteServiceProvider maps, route files) and dispatches FABRICATED
 * requests through the full routing + middleware + response pipeline — the same path a
 * real request takes — returning a {@see TestResponse} to assert against:
 *
 *     use Eyika\Atom\Framework\Support\Testing\TestCase;
 *
 *     class HomeTest extends TestCase
 *     {
 *         public function test_home_page(): void
 *         {
 *             $this->get('/')->assertOk()->assertBodyContains('Hello');
 *         }
 *
 *         public function test_api(): void
 *         {
 *             $this->postJson('/users', ['name' => 'Ada'])
 *                  ->assertCreated()
 *                  ->assertJsonFragment(['message' => 'created']);
 *         }
 *     }
 *
 * Requires base_path() to resolve (set $GLOBALS['base_path'] in your tests bootstrap).
 * Per-request static state is reset between calls via Application::flushRequestState(),
 * so one test never leaks into the next.
 */
abstract class TestCase extends PHPUnitTestCase
{
    protected Application $app;

    /** Booted once for the whole run; the route table is snapshotted alongside it. */
    protected static ?Application $bootedApp = null;
    /**
     * Route table snapshot PER map (keyed by map name). Each map's route file is loaded
     * in isolation so that maps sharing a path (e.g. both web.php and api.php define "/")
     * don't collide in one merged table — mirroring how Server::handle loads only the
     * resolved map's file per request.
     *
     * @var array<string, array<string,mixed>>
     */
    protected static array $routeSnapshots = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->app = $this->bootApplication();
    }

    protected function tearDown(): void
    {
        Route::flushRequestState();
        Facade::clearResolvedInstances();
        BaseResponse::captureOutput(false);
        BaseResponse::resetCapture();
        parent::tearDown();
    }

    /**
     * Boot the application a single time (providers + maps + route files) and cache it.
     * The route file is require_once'd once, so we snapshot the table and restore it
     * before each request rather than re-requiring a file that won't re-execute.
     */
    protected function bootApplication(): Application
    {
        if (self::$bootedApp !== null) {
            Facade::setFacadeApplication(self::$bootedApp);
            return self::$bootedApp;
        }

        /** @var Application $app */
        $app = require base_path('bootstrap/app.php');
        new Server($app);
        $app->registerProviders();

        // Load each map's route file in isolation and snapshot it separately, so shared
        // paths across maps don't overwrite each other in one merged table.
        foreach (Route::maps() as $map) {
            if ($map->getFile() !== null) {
                Route::clearRegistered();
                Route::loadRoutesFile($map->getName(), $map->getFile());
                self::$routeSnapshots[$map->getName()] = Route::getRoutes();
            }
        }
        Route::clearRegistered();

        return self::$bootedApp = $app;
    }

    // --- Request helpers -----------------------------------------------------

    protected function get(string $uri, array $headers = []): TestResponse
    {
        return $this->call('GET', $uri, [], [], $headers);
    }

    protected function post(string $uri, array $body = [], array $headers = []): TestResponse
    {
        return $this->call('POST', $uri, [], $body, $headers);
    }

    /** POST (or any verb) a JSON body — routes to the api map and injects php://input. */
    protected function postJson(string $uri, array $json = [], array $headers = []): TestResponse
    {
        return $this->call('POST', $uri, [], [], $headers, [], $json);
    }

    protected function getJson(string $uri, array $headers = []): TestResponse
    {
        return $this->call('GET', $uri, [], [], $headers, [], []);
    }

    /**
     * Fabricate a request and dispatch it through the real map pipeline.
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
        BaseResponse::captureOutput(true);
        BaseResponse::resetCapture();

        parse_str((string) parse_url($uri, PHP_URL_QUERY), $parsedQuery);
        $query = array_merge($parsedQuery, $query);

        $headerMap = ['Host' => 'localhost'];
        foreach ($headers as $k => $v) {
            $headerMap[$k] = $v;
        }
        $server = [
            'REQUEST_METHOD' => strtoupper($method),
            'REQUEST_URI'    => $uri,
            'HTTP_HOST'      => $headerMap['Host'],
            'SERVER_PORT'    => '80',
            'SERVER_NAME'    => 'localhost',
            'HTTPS'          => '',
        ];
        // A JSON call implies an "Accept: application/json" so it resolves to the api map.
        if ($json !== null && !isset($headerMap['Accept'])) {
            $headerMap['Accept'] = 'application/json';
        }
        if ($json !== null) {
            $server['CONTENT_TYPE'] = 'application/json';
            $headerMap['Content-Type'] = 'application/json';
        }
        foreach ($headerMap as $k => $v) {
            $server['HTTP_' . strtoupper(str_replace('-', '_', $k))] = $v;
        }

        $source = [
            'server'  => $server,
            'query'   => $query,
            'post'    => $body,
            'cookies' => $cookies,
            'files'   => [],
            'headers' => $headerMap,
        ];
        if ($json !== null) {
            $source['rawBody'] = json_encode($json);
        }

        $request = new Request($source);
        $this->app->instance('request', $request);
        // Fresh response objects per request (shared mutable singletons otherwise leak).
        $this->app->instance('response', new Response());
        $this->app->instance('json_response', new JsonResponse());
        Facade::clearResolvedInstances();

        try {
            $map = Route::resolveMapFor($request);
            if ($map !== null) {
                // Restore only the resolved map's routes (see $routeSnapshots).
                Route::setRoutes(self::$routeSnapshots[$map->getName()] ?? []);
                if ($map->getFile() !== null) {
                    Route::isApiRequest($map->isStateless());
                    if ($middleware = $map->getMiddleware()) {
                        $this->loadMiddlewares($middleware);
                    }
                }
            }
            Route::dispatch($request);
        } catch (Throwable $e) {
            $this->renderException($request, $e);
        }

        $response = new TestResponse(
            BaseResponse::capturedBody(),
            BaseResponse::capturedStatus() ?? 200,
            BaseResponse::capturedHeaders()
        );

        Route::flushRequestState();
        BaseResponse::captureOutput(false);

        return $response;
    }

    protected function loadMiddlewares(string $type): void
    {
        /** @var Kernel $kernel */
        $kernel = $this->app->make(Kernel::class);

        Route::$middlewareAliases = $kernel->getMiddlewareAliases();
        $middlewares = $kernel->getMiddlewares();

        $groups = $kernel->getMiddlewareGroups();
        if (isset($groups[$type])) {
            array_push($middlewares, ...$groups[$type]);
        }

        Route::$defaultMiddlewares = $middlewares;
        Route::$middlewarePriority = $kernel->getMiddlewarePriority();
    }

    protected function renderException(Request $request, Throwable $e): void
    {
        try {
            if ($this->app->bound(ExceptionHandler::class)) {
                $this->app->make(ExceptionHandler::class)->render($request, $e)->send();
                return;
            }
        } catch (Throwable $ignored) {
            // fall through
        }
        // Re-throw so the failing test surfaces the real error rather than a blank 500.
        throw $e;
    }
}
