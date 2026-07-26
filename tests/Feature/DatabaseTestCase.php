<?php

namespace Eyika\Atom\Framework\Tests\Feature;

use Eyika\Atom\Framework\Foundation\Application;
use Eyika\Atom\Framework\Support\Database\Connection;
use Eyika\Atom\Framework\Support\Facade\Facade;
use PHPUnit\Framework\TestCase;
use Throwable;

/**
 * Base for DB-backed Feature tests: boots the app, binds a real Connection to the
 * `db.connection` container slot (so the DatabaseConnection facade + Model/DB
 * builders hit MySQL), and manages isolated `atomtest_*` schema per test. Skips
 * gracefully when the database is unavailable.
 */
abstract class DatabaseTestCase extends TestCase
{
    protected Application $app;
    protected Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app = new Application($GLOBALS['base_path'], true);
        Facade::setFacadeApplication($this->app);

        $this->connection = new Connection(config('database'));
        $this->app->instance('db.connection', $this->connection);
        Facade::clearResolvedInstances();

        try {
            $this->connection->connect();
        } catch (Throwable $e) {
            $this->markTestSkipped('Database not available: ' . $e->getMessage());
        }

        $this->createSchema();
    }

    protected function tearDown(): void
    {
        try {
            $this->dropSchema();
        } catch (Throwable $e) {
            // ignore teardown failures
        }
        Facade::clearResolvedInstances();
        parent::tearDown();
    }

    /** Run raw SQL against the test connection. */
    protected function raw(string $sql): void
    {
        $this->connection->exec($sql);
    }

    abstract protected function createSchema(): void;

    abstract protected function dropSchema(): void;
}
