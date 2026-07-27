<?php

namespace Eyika\Atom\Framework\Tests\Unit\Database;

use Eyika\Atom\Framework\Support\Database\Connection;
use PDO;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;

/**
 * Covers PERF-03: persistent PDO connections are opt-in via
 * database.connections.<driver>.persistent, OFF by default (persistent handles can
 * carry state across requests). Base PDO options are always present.
 */
class ConnectionOptionsTest extends TestCase
{
    private function optionsFor(array $config, string $driver = 'mysql'): array
    {
        $conn = new Connection($config);

        $d = new ReflectionProperty(Connection::class, 'driver');
        $d->setAccessible(true);
        $d->setValue($conn, $driver);

        $m = new ReflectionMethod(Connection::class, 'getOptions');
        $m->setAccessible(true);

        return $m->invoke($conn);
    }

    public function test_persistent_is_off_by_default(): void
    {
        $opts = $this->optionsFor(['connections' => ['mysql' => []]]);
        $this->assertArrayNotHasKey(PDO::ATTR_PERSISTENT, $opts);
    }

    public function test_persistent_enabled_when_configured(): void
    {
        $opts = $this->optionsFor(['connections' => ['mysql' => ['persistent' => true]]]);
        $this->assertArrayHasKey(PDO::ATTR_PERSISTENT, $opts);
        $this->assertTrue($opts[PDO::ATTR_PERSISTENT]);
    }

    public function test_base_options_always_present(): void
    {
        $opts = $this->optionsFor(['connections' => ['mysql' => []]]);
        $this->assertSame(PDO::ERRMODE_EXCEPTION, $opts[PDO::ATTR_ERRMODE]);
        $this->assertSame(PDO::FETCH_ASSOC, $opts[PDO::ATTR_DEFAULT_FETCH_MODE]);
        $this->assertFalse($opts[PDO::ATTR_EMULATE_PREPARES]);
    }
}
