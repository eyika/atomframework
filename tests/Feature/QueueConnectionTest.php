<?php

namespace Eyika\Atom\Framework\Tests\Feature;

use Eyika\Atom\Framework\Foundation\Console\Job_Queue;
use Eyika\Atom\Framework\Foundation\Console\QueueConnector;
use Eyika\Atom\Framework\Support\Database\Connection;
use Exception;
use PDO;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Reported by a downstream consumer as a blocker: queued jobs always reached port **3306**.
 *
 * `ShouldQueue::dispatch()` hand-assembled `"mysql:dbname=…;host=…"` and never wrote a port, so an
 * engine on any other port was silently not the database the application itself was using. With a
 * second server running beside an older one — the reporter's case: MariaDB on 3307 next to the old
 * MySQL on 3306 — every dispatch presented the NEW credentials to the OLD host, which answers
 * "access denied". The symptom reads as a password problem and is a routing problem.
 *
 * Three call sites hand-rolled that DSN, not one: `dispatch()`, `init()` and the worker's
 * `JobRunner::makeQueue()`. They also dropped the charset and every configured PDO option,
 * `ERRMODE_EXCEPTION` among them — so a queue write that failed did so silently.
 *
 * `dispatch()` compounded it by reading `env()` rather than `config()`. `env()` sees `$_ENV` only,
 * and php.ini ships `variables_order="GPCS"`, so an exported shell variable never arrives; an app
 * that configures its database in `config/database.php` with no `.env` got a DSN of empty strings.
 * That is the part that makes this a framework bug rather than a deployment mistake: there was no
 * way to configure it correctly.
 */
class QueueConnectionTest extends TestCase
{
    private function dsnFor(array $config, string $driver): string
    {
        $method = new ReflectionMethod(Connection::class, 'dsnFor');
        $method->setAccessible(true);

        return $method->invoke(null, $config, $driver);
    }

    private function optionsFor(array $config, string $driver): array
    {
        $method = new ReflectionMethod(Connection::class, 'optionsFor');
        $method->setAccessible(true);

        return $method->invoke(null, $config, $driver);
    }

    private function config(array $overrides = []): array
    {
        return [
            'default' => 'mysql',
            'connections' => [
                'mysql' => array_merge([
                    'driver' => 'mysql',
                    'host' => 'db.internal',
                    'port' => '3307',
                    'database' => 'shop',
                    'username' => 'app',
                    'password' => 'secret',
                    'charset' => 'utf8mb4',
                ], $overrides),
            ],
        ];
    }

    // ---------------------------------------------------------------- the reported bug

    /** The whole report in one assertion: the configured port has to reach the DSN. */
    public function test_the_configured_port_reaches_the_dsn(): void
    {
        $this->assertStringContainsString('port=3307', $this->dsnFor($this->config(), 'mysql'));
    }

    public function test_the_dsn_carries_host_database_and_charset_too(): void
    {
        $dsn = $this->dsnFor($this->config(), 'mysql');

        $this->assertStringContainsString('host=db.internal', $dsn);
        $this->assertStringContainsString('dbname=shop', $dsn);
        $this->assertStringContainsString('charset=utf8mb4', $dsn);
    }

    /** Only when no port is configured at all may it fall back. */
    public function test_the_port_defaults_only_when_absent(): void
    {
        $config = $this->config();
        unset($config['connections']['mysql']['port']);

        $this->assertStringContainsString('port=3306', $this->dsnFor($config, 'mysql'));
    }

    /**
     * The silent half: a hand-rolled PDO gets PDO's defaults, so `ERRMODE_SILENT` — a failing
     * queue write returns false and the dispatcher carries on as though the job were queued.
     */
    public function test_a_queue_connection_raises_rather_than_failing_silently(): void
    {
        $options = $this->optionsFor($this->config(), 'mysql');

        $this->assertSame(PDO::ERRMODE_EXCEPTION, $options[PDO::ATTR_ERRMODE]);
    }

    /** A connection's configured options (TLS, init command) must not be dropped either. */
    public function test_configured_pdo_options_survive(): void
    {
        $config = $this->config(['options' => [PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+00:00'"]]);

        $this->assertSame(
            "SET time_zone = '+00:00'",
            $this->optionsFor($config, 'mysql')[PDO::MYSQL_ATTR_INIT_COMMAND] ?? null
        );
    }

    // ---------------------------------------------------------------- misconfiguration is loud

    public function test_a_missing_connection_names_itself(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches("/No 'mysql' database connection is configured/");

        Connection::makePdo(['default' => 'mysql', 'connections' => []], 'mysql');
    }

    // ---------------------------------------------------------------- the call sites

    /**
     * The regression guard that matters: no queue code may build a DSN by hand again.
     *
     * Asserted on the source, because the failure mode is textual — a string concatenation that
     * forgets a parameter — and a behavioural test would need the misconfigured second server the
     * reporter happened to have.
     */
    public function test_no_queue_code_hand_builds_a_dsn(): void
    {
        $files = [
            'src/Foundation/Console/Concerns/ShouldQueue.php',
            'src/Foundation/Console/JobRunner.php',
        ];

        foreach ($files as $file) {
            $source = file_get_contents(dirname(__DIR__, 2) . '/' . $file);

            // `new PDO(` rather than the DSN text: the docblocks in these files quote the old
            // string to explain what went wrong, and a test that greps for it would be failed by
            // its own explanation. Constructing a PDO at all is the thing that must not come back.
            $this->assertStringNotContainsString(
                'new PDO(',
                $source,
                "{$file} builds its own PDO; use Connection::makePdo() so the port cannot be lost"
            );
            $this->assertStringNotContainsString(
                "env('DB_",
                $source,
                "{$file} reads env() for connection details; config() is the configured connection"
            );
        }
    }

    /**
     * There were three hand-rolled connections that had to agree and did not. There is now one
     * place that builds them, and both the dispatcher and the worker go through it.
     */
    public function test_the_dispatcher_and_the_worker_use_the_same_builder(): void
    {
        foreach ([
            'src/Foundation/Console/Concerns/ShouldQueue.php',
            'src/Foundation/Console/JobRunner.php',
        ] as $file) {
            $this->assertStringContainsString(
                'QueueConnector::make()',
                file_get_contents(dirname(__DIR__, 2) . '/' . $file),
                "{$file} must build its queue through the one connector, not a connection of its own"
            );
        }

        $this->assertStringContainsString(
            'Connection::makePdo(config(\'database\')',
            file_get_contents(dirname(__DIR__, 2) . '/src/Foundation/Console/QueueConnector.php'),
            'the connector must build from the application\'s own database config'
        );
    }

    // ---------------------------------------------------------------- the queue follows the app

    /**
     * The wiring half of driver support.
     *
     * `Job_Queue` gained per-driver DDL and a dialect taken from the connection, but both call
     * sites still constructed `QUEUE_TYPE_MYSQL` and read the `mysql` connection — so the
     * portability was theoretical: whatever database the application used, the queue asked for
     * MySQL, which is also what the docs had to warn readers about.
     */
    public function test_the_queue_uses_the_applications_default_connection(): void
    {
        $this->assertSame(config('database.default'), QueueConnector::connectionName());
    }

    /** `database.default` names a CONNECTION, which need not be named after its driver. */
    public function test_the_dialect_comes_from_the_connections_driver(): void
    {
        $connection = config('database.default');

        $this->assertSame(config("database.connections.{$connection}.driver"), QueueConnector::driver());
    }

    public function test_the_connector_builds_a_sql_backed_queue(): void
    {
        $this->assertTrue(QueueConnector::make()->isSqlQueueType());
    }

    /**
     * An unrecognised type was accepted and then matched no arm of any switch, so every call was a
     * silent no-op — jobs vanished on dispatch and the worker found an empty queue.
     */
    public function test_an_unsupported_queue_type_is_refused(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches("/Unsupported queue type 'oracle'/");

        new Job_Queue('oracle', []);
    }

    // ---------------------------------------------------------------- driver-derived dialect

    /** The queue asks the handle what it is, rather than trusting the label it was constructed with. */
    public function test_the_queue_derives_its_dialect_from_the_connection(): void
    {
        $queue = new Job_Queue(Job_Queue::QUEUE_TYPE_MYSQL, []);
        $queue->addQueueConnection(new PDO('sqlite::memory:'));

        $method = new ReflectionMethod(Job_Queue::class, 'sqlDriver');
        $method->setAccessible(true);

        $this->assertSame('sqlite', $method->invoke($queue));
    }
}
