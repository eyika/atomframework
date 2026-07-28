<?php

namespace Eyika\Atom\Framework\Support\Testing;

use Eyika\Atom\Framework\Foundation\Application;
use Eyika\Atom\Framework\Support\Database\Connection;
use Eyika\Atom\Framework\Support\Facade\Facade;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use Throwable;

/**
 * Base class for database-backed tests. It boots the application, binds a real
 * Connection to the `db.connection` slot (so the DatabaseConnection facade + Model/DB
 * builders hit your database), and lets each test manage isolated schema via
 * createSchema()/dropSchema(). Skips gracefully when the database is unavailable.
 *
 *     use Eyika\Atom\Framework\Support\Testing\DatabaseTestCase;
 *
 *     class ItemTest extends DatabaseTestCase
 *     {
 *         protected function createSchema(): void
 *         {
 *             $this->raw('CREATE TABLE IF NOT EXISTS test_items (id INT PRIMARY KEY, name VARCHAR(50))');
 *         }
 *         protected function dropSchema(): void
 *         {
 *             $this->raw('DROP TABLE IF EXISTS test_items');
 *         }
 *
 *         public function test_insert(): void { ... }
 *     }
 *
 * Requires base_path() to resolve (set $GLOBALS['base_path'] in your tests bootstrap).
 */
abstract class DatabaseTestCase extends PHPUnitTestCase
{
    protected Application $app;
    protected Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app = new Application(base_path(), true);
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
