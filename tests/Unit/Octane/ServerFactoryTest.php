<?php

namespace Eyika\Atom\Framework\Tests\Unit\Octane;

use Eyika\Atom\Framework\Foundation\Application;
use Eyika\Atom\Octane\ServerFactory;
use Eyika\Atom\Octane\Servers\FrankenPhpServer;
use Eyika\Atom\Octane\Servers\NativeServer;
use Eyika\Atom\Octane\Servers\RoadRunnerServer;
use Eyika\Atom\Octane\Servers\SwooleServer;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

$octane = dirname(__DIR__, 4) . '/atom-octane/src';
require_once $octane . '/Http/HttpMessage.php';
require_once $octane . '/Contracts/Server.php';
require_once $octane . '/Worker.php';
require_once $octane . '/Servers/NativeServer.php';
require_once $octane . '/Servers/SwooleServer.php';
require_once $octane . '/Servers/RoadRunnerServer.php';
require_once $octane . '/Servers/FrankenPhpServer.php';
require_once $octane . '/ServerFactory.php';

/**
 * The factory resolves config/overrides into the right runtime (without starting it —
 * constructing a server needs no extension; only start() does), and resolves the worker
 * count.
 */
class ServerFactoryTest extends TestCase
{
    private function app(): Application
    {
        // The factory only stores the app on the Worker; no methods are called on it.
        return (new ReflectionClass(Application::class))->newInstanceWithoutConstructor();
    }

    /** @return array<string,mixed> all options so the factory never falls back to config(). */
    private function opts(string $server): array
    {
        return [
            'server' => $server, 'host' => '127.0.0.1', 'port' => 8090, 'workers' => 2,
            'max_requests' => 500, 'max_memory' => 0, 'request_timeout' => 30,
            'keep_alive' => true, 'keep_alive_timeout' => 5, 'max_request_size' => 1000,
        ];
    }

    public function test_selects_the_configured_runtime(): void
    {
        $app = $this->app();
        $this->assertInstanceOf(NativeServer::class, ServerFactory::make($app, $this->opts('native')));
        $this->assertInstanceOf(SwooleServer::class, ServerFactory::make($app, $this->opts('swoole')));
        $this->assertInstanceOf(RoadRunnerServer::class, ServerFactory::make($app, $this->opts('roadrunner')));
        $this->assertInstanceOf(FrankenPhpServer::class, ServerFactory::make($app, $this->opts('frankenphp')));
        // aliases
        $this->assertInstanceOf(RoadRunnerServer::class, ServerFactory::make($app, $this->opts('rr')));
    }

    public function test_unknown_runtime_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ServerFactory::make($this->app(), $this->opts('nope'));
    }

    public function test_server_name(): void
    {
        $this->assertSame('frankenphp', ServerFactory::make($this->app(), $this->opts('frankenphp'))->name());
    }

    public function test_resolve_workers(): void
    {
        $this->assertSame(4, ServerFactory::resolveWorkers(4));
        $this->assertSame(3, ServerFactory::resolveWorkers('3'));
        $this->assertGreaterThanOrEqual(1, ServerFactory::resolveWorkers('auto'));
    }
}
