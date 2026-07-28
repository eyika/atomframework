<?php

namespace Eyika\Atom\Framework\Tests\Feature;

use Eyika\Atom\Framework\Http\BaseResponse;
use Eyika\Atom\Framework\Http\Route;
use Eyika\Atom\Framework\Support\Facade\JsonResponse;
use Eyika\Atom\Octane\HttpServer;
use Eyika\Atom\Octane\Worker;

// The atom-octane package is a sibling repo (not in this suite's autoloader), so pull in
// the classes under test directly. Their only dependencies are framework classes, which
// ARE autoloaded. Path: atomframework/tests/Feature -> eyika/ -> atom-octane/src/*
require_once dirname(__DIR__, 3) . '/atom-octane/src/Worker.php';
require_once dirname(__DIR__, 3) . '/atom-octane/src/HttpServer.php';

/**
 * Proves the Octane-style worker end-to-end against the fixture app: it boots the
 * application ONCE and then serves multiple injected requests, capturing each response
 * and scrubbing per-request state in between so nothing leaks from one request to the
 * next. This is the payoff of the whole WRK worker-safety cluster.
 */
class OctaneWorkerTest extends IntegrationTestCase
{
    protected function tearDown(): void
    {
        // The worker turns response capture ON globally (static); reset it so later
        // tests that expect native emit behaviour aren't affected by test ordering.
        BaseResponse::captureOutput(false);
        BaseResponse::resetCapture();
        Route::flushMaps(); // maps are static — don't leak into other tests
        parent::tearDown();
    }

    public function test_boots_once_and_serves_isolated_requests(): void
    {
        // Registered before boot → captured in the worker's route snapshot.
        Route::get('/echo', function ($request) {
            return JsonResponse::ok('ok', ['name' => $request->query('name', 'none')]);
        });

        $worker = new Worker($this->app);

        $first  = $worker->handle($this->source('GET', '/echo?name=alice'));
        $second = $worker->handle($this->source('GET', '/echo?name=bob'));

        // Both requests served successfully off the same booted kernel.
        $this->assertSame(200, $first['status']);
        $this->assertStringContainsString('alice', $first['body']);

        $this->assertSame(200, $second['status']);
        $this->assertStringContainsString('bob', $second['body']);

        // The crucial worker-safety assertion: request 1's data did NOT bleed into
        // request 2's captured response.
        $this->assertStringNotContainsString('alice', $second['body']);

        // A JSON content-type header was captured (response emitted through the object).
        $this->assertNotEmpty($second['headers']);
    }

    public function test_a_not_found_route_returns_404_and_the_worker_survives(): void
    {
        Route::get('/exists', fn ($request) => JsonResponse::ok('ok', ['ok' => true]));

        $worker = new Worker($this->app);

        $missing = $worker->handle($this->source('GET', '/nope'));
        $this->assertSame(404, $missing['status']);

        // The worker keeps serving after handling an error request.
        $ok = $worker->handle($this->source('GET', '/exists'));
        $this->assertSame(200, $ok['status']);
        $this->assertStringContainsString('ok', $ok['body']);
    }

    public function test_http_layer_parses_a_request_and_builds_a_response(): void
    {
        $server = new HttpServer(new Worker($this->app));

        $raw = "POST /submit?ref=1 HTTP/1.1\r\n"
            . "Host: example.test\r\n"
            . "Content-Type: application/x-www-form-urlencoded\r\n"
            . "Cookie: sid=abc; theme=dark\r\n"
            . "Content-Length: 7\r\n\r\n"
            . "a=1&b=2";

        $source = $server->parseRequest($raw);

        $this->assertSame('POST', $source['server']['REQUEST_METHOD']);
        $this->assertSame('/submit?ref=1', $source['server']['REQUEST_URI']);
        $this->assertSame(['ref' => '1'], $source['query']);
        $this->assertSame(['a' => '1', 'b' => '2'], $source['post']);
        $this->assertSame('abc', $source['cookies']['sid']);
        $this->assertSame('dark', $source['cookies']['theme']);
        $this->assertSame('example.test', $source['server']['HTTP_HOST']);

        $http = $server->buildHttpResponse([
            'status'  => 201,
            'headers' => ['Content-Type: application/json'],
            'body'    => '{"ok":true}',
        ]);

        $this->assertStringStartsWith("HTTP/1.1 201 Created\r\n", $http);
        $this->assertStringContainsString("Content-Type: application/json\r\n", $http);
        $this->assertStringContainsString("Content-Length: 11\r\n", $http); // auto-added
        $this->assertStringEndsWith("\r\n\r\n" . '{"ok":true}', $http);
    }

    public function test_maps_sharing_a_path_do_not_collide(): void
    {
        // Two maps whose route files BOTH define "/". A single merged route table would let
        // one overwrite the other; the worker's per-map snapshots keep them separate.
        $dir = sys_get_temp_dir() . '/atomtest_octane_' . uniqid('', true);
        mkdir($dir, 0775, true);
        $webFile = $dir . '/web.php';
        $apiFile = $dir . '/api.php';
        file_put_contents($webFile, "<?php use Eyika\\Atom\\Framework\\Http\\Route; Route::get('/', fn () => 'WEB-ROOT');");
        file_put_contents($apiFile, "<?php use Eyika\\Atom\\Framework\\Http\\Route; Route::get('/', fn () => 'API-ROOT');");

        Route::map('api')->stateless()->when(fn ($request) => $request->wantsJson())->load($apiFile);
        Route::map('web')->load($webFile);

        $worker = new Worker($this->app);

        $web = $worker->handle($this->source('GET', '/'));
        $api = $worker->handle($this->jsonSource('GET', '/'));

        $this->assertStringContainsString('WEB-ROOT', $web['body']);
        $this->assertStringNotContainsString('API-ROOT', $web['body']);

        $this->assertStringContainsString('API-ROOT', $api['body']);
        $this->assertStringNotContainsString('WEB-ROOT', $api['body']);

        @unlink($webFile);
        @unlink($apiFile);
        @rmdir($dir);
    }

    public function test_worker_recycles_after_its_request_quota(): void
    {
        Route::get('/r', fn ($request) => JsonResponse::ok('ok'));
        $worker = new Worker($this->app, maxRequests: 2);

        $this->assertFalse($worker->shouldRecycle());

        $worker->handle($this->source('GET', '/r'));
        $this->assertSame(1, $worker->requestsHandled());
        $this->assertFalse($worker->shouldRecycle());

        $worker->handle($this->source('GET', '/r'));
        $this->assertSame(2, $worker->requestsHandled());
        $this->assertTrue($worker->shouldRecycle()); // quota reached → recycle
    }

    /** A request source that asks for JSON (so the api map's matcher accepts it). */
    private function jsonSource(string $method, string $target): array
    {
        $source = $this->source($method, $target);
        $source['server']['HTTP_ACCEPT'] = 'application/json';
        $source['headers']['Accept'] = 'application/json';
        return $source;
    }

    /** Build a Worker request source array (see Request::__construct) from method + target. */
    private function source(string $method, string $target): array
    {
        $parts = parse_url($target) ?: [];
        parse_str($parts['query'] ?? '', $query);

        return [
            'server' => [
                'REQUEST_METHOD' => $method,
                'REQUEST_URI'    => $target,
                'HTTP_HOST'      => 'localhost',
            ],
            'query'   => $query,
            'post'    => [],
            'cookies' => [],
            'files'   => [],
            'headers' => ['Host' => 'localhost'],
        ];
    }
}
