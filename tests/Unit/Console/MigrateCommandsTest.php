<?php

namespace Eyika\Atom\Framework\Tests\Unit\Console;

use Eyika\Atom\Framework\Foundation\Console\Commands\Db\MigrateStatus;
use Eyika\Atom\Framework\Foundation\Console\Commands\Db\Rollback;
use Eyika\Atom\Framework\Support\Database\Connection;
use Eyika\Atom\Framework\Support\Database\Schema\Blueprint;
use Eyika\Atom\Framework\Support\Database\Schema\Schema;
use Eyika\Atom\Framework\Support\Facade\DatabaseConnection;
use PHPUnit\Framework\TestCase;

/**
 * MigrateStatus.info()/table() write to php://stdout (not output-bufferable), so the recording
 * subclass captures the rows the command computes instead. Rollback --pretend is proven by DB
 * state: the migrations table and schema must be untouched.
 */
class RecordingMigrateStatus extends MigrateStatus
{
    /** @var array<int, array{0:string,1:string}> The rows passed to table(). */
    public array $captured = [];

    public function info(string $message, array $context = [], $to_log_file = false): void
    {
        // silence console noise during the test
    }

    protected function table(array $headers, array $rows): void
    {
        $this->captured = $rows;
    }
}

/** Silences console output so the pretend rollback test stays quiet. */
class QuietRollback extends Rollback
{
    public function info(string $message, array $context = [], $to_log_file = false): void
    {
    }
}

class MigrateCommandsTest extends TestCase
{
    private string $baseDir;
    private mixed $origBasePath;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite is not enabled.');
        }

        // Preserve the bootstrap-set base_path — other suites (ContainerTest, ConfigTest) rely on
        // it, so we must restore it in tearDown rather than clobber/unset it.
        $this->origBasePath = $GLOBALS['base_path'] ?? null;

        // Fresh in-memory SQLite as the active connection/grammar for the whole stack.
        $conn = new Connection([
            'default'     => 'sqlite',
            'connections' => ['sqlite' => ['database' => ':memory:']],
        ]);
        $conn->connect();
        DatabaseConnection::swap($conn);

        // A temp base_path so database_path('migrations/*.php') resolves to real files.
        $this->baseDir = sys_get_temp_dir() . '/atom_migrate_test_' . uniqid();
        @mkdir($this->baseDir . '/database/migrations', 0777, true);
        $GLOBALS['base_path'] = $this->baseDir;

        Schema::create('migrations', function (Blueprint $t) {
            $t->id();
            $t->string('migration', 191)->notNullable();
            $t->integer('batch')->notNullable();
        });
    }

    protected function tearDown(): void
    {
        // setUp() skips before the temp dir and base_path are set when pdo_sqlite is missing, but
        // PHPUnit runs tearDown regardless — bail out before touching uninitialised properties,
        // otherwise the skip surfaces as an Error.
        if (!isset($this->baseDir)) {
            parent::tearDown();
            return;
        }

        // Remove temp migration files + dirs.
        foreach (glob($this->baseDir . '/database/migrations/*.php') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->baseDir . '/database/migrations');
        @rmdir($this->baseDir . '/database');
        @rmdir($this->baseDir);
        if ($this->origBasePath === null) {
            unset($GLOBALS['base_path']);
        } else {
            $GLOBALS['base_path'] = $this->origBasePath;
        }
        parent::tearDown();
    }

    private function migrationFile(string $name): void
    {
        file_put_contents($this->baseDir . "/database/migrations/{$name}.php", "<?php return null;\n");
    }

    /**
     * Regression: MigrateStatus compared a name string against ROWS (['migration' => name]), so
     * every migration showed ❌. After flattening, an already-run migration shows ✔️ and an
     * unrun one shows ❌.
     */
    public function test_status_marks_already_run_migrations_as_migrated(): void
    {
        $this->migrationFile('2026_01_01_000000_create_foo');   // will be recorded as run
        $this->migrationFile('2026_01_02_000000_create_bar');   // never run

        DatabaseConnection::insert('migrations', ['migration' => '2026_01_01_000000_create_foo', 'batch' => 1]);

        $cmd = new RecordingMigrateStatus();
        $this->assertTrue($cmd->handle());

        $status = [];
        foreach ($cmd->captured as [$name, $mark]) {
            $status[$name] = $mark;
        }

        $this->assertStringContainsString('Yes', $status['2026_01_01_000000_create_foo'] ?? '', 'a run migration must show migrated');
        $this->assertStringContainsString('No', $status['2026_01_02_000000_create_bar'] ?? '', 'an unrun migration must show not-migrated');
    }

    /**
     * Requirement: `migrate:rollback --step=1 --pretend` leaves the migrations table and the
     * schema unchanged — it only lists what would roll back.
     */
    public function test_rollback_pretend_changes_nothing(): void
    {
        // A schema object a real rollback's down() would drop.
        Schema::create('demo_widgets', function (Blueprint $t) {
            $t->id();
            $t->string('label');
        });

        DatabaseConnection::insert('migrations', ['migration' => 'm_one', 'batch' => 1]);
        DatabaseConnection::insert('migrations', ['migration' => 'm_two', 'batch' => 1]);

        $cmd = (new QuietRollback())->setArguments(['--step=1', '--pretend']);
        $this->assertTrue($cmd->handle());

        // Migrations table untouched (both rows survive) and the schema object still exists.
        $this->assertSame(2, DatabaseConnection::count('migrations'), 'pretend must not delete migration rows');
        $this->assertTrue(Schema::hasTable('demo_widgets'), 'pretend must not run down()/drop schema');
    }
}
