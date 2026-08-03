<?php

namespace Eyika\Atom\Framework\Tests\Unit\Console;

use Eyika\Atom\Framework\Foundation\Console\Concerns\ResolvesMigrationPaths;
use Eyika\Atom\Framework\Foundation\ServiceProvider;
use PHPUnit\Framework\TestCase;

/**
 * loadMigrationsFrom() is protected static — a package registers its directory from inside its
 * own provider. This stands in for one so the test registers the way a real package does.
 */
class MigrationPathTestProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    /**
     * loadMigrationsFrom() is a protected INSTANCE method (only its storage is static), and the
     * base constructor wants an ApplicationInterface. Skipping the constructor keeps this a unit
     * test — registration only writes to a static array, so no container is involved.
     */
    public static function addPath(string $path): void
    {
        $provider = (new \ReflectionClass(static::class))->newInstanceWithoutConstructor();
        $provider->loadMigrationsFrom($path);
    }
}

/**
 * PKG-01 follow-up: `migrate` honoured package migration directories registered with
 * MigrationPathTestProvider::addPath(), but its siblings did not — `rollback` resolved a
 * migration only against base_path('database/migrations') and threw "Migration file not found"
 * for a package migration it had itself applied, and `migrate:status` globbed only the app
 * directory so package migrations never appeared at all.
 *
 * Discovery is now shared, so all the migrate commands agree on where migrations live.
 */
class MigrationPathsTest extends TestCase
{
    private string $base;
    private mixed $origBasePath;

    /** Exposes the trait's protected helpers. */
    private object $resolver;

    protected function setUp(): void
    {
        $this->origBasePath = $GLOBALS['base_path'] ?? null;

        $this->base = sys_get_temp_dir() . '/atom_migpaths_' . uniqid();
        @mkdir($this->base . '/database/migrations', 0777, true);
        @mkdir($this->base . '/pkg/migrations', 0777, true);
        $GLOBALS['base_path'] = $this->base;

        file_put_contents($this->base . '/database/migrations/2026_01_01_000001_app_one.php', '<?php return null;');
        file_put_contents($this->base . '/pkg/migrations/2026_01_01_000002_pkg_one.php', '<?php return null;');

        ServiceProvider::flushPackageRegistrations();

        $this->resolver = new class {
            use ResolvesMigrationPaths {
                migrationDirectories as public;
                gatherMigrationFiles as public;
                findMigrationFile as public;
            }
        };
    }

    protected function tearDown(): void
    {
        ServiceProvider::flushPackageRegistrations();

        foreach (['/database/migrations', '/pkg/migrations'] as $dir) {
            foreach (glob($this->base . $dir . '/*.php') ?: [] as $f) {
                @unlink($f);
            }
            @rmdir($this->base . $dir);
        }
        @rmdir($this->base . '/database');
        @rmdir($this->base . '/pkg');
        @rmdir($this->base);

        if ($this->origBasePath === null) {
            unset($GLOBALS['base_path']);
        } else {
            $GLOBALS['base_path'] = $this->origBasePath;
        }

        parent::tearDown();
    }

    public function test_only_the_app_directory_is_used_when_no_package_registers_one(): void
    {
        $this->assertSame(
            [$this->base . '/database/migrations'],
            $this->resolver->migrationDirectories()
        );
    }

    public function test_a_registered_package_directory_is_included_after_the_app(): void
    {
        MigrationPathTestProvider::addPath($this->base . '/pkg/migrations');

        $dirs = $this->resolver->migrationDirectories();

        // App first so its migrations keep running before package ones.
        $this->assertSame($this->base . '/database/migrations', $dirs[0]);
        $this->assertContains($this->base . '/pkg/migrations', $dirs);
    }

    public function test_gathered_files_span_app_and_package_directories(): void
    {
        MigrationPathTestProvider::addPath($this->base . '/pkg/migrations');

        $names = array_map(fn ($f) => basename($f), $this->resolver->gatherMigrationFiles());

        $this->assertContains('2026_01_01_000001_app_one.php', $names);
        $this->assertContains('2026_01_01_000002_pkg_one.php', $names);
    }

    /** The rollback case: a package migration must be locatable by name. */
    public function test_a_package_migration_is_found_by_name(): void
    {
        MigrationPathTestProvider::addPath($this->base . '/pkg/migrations');

        $found = $this->resolver->findMigrationFile('2026_01_01_000002_pkg_one');

        $this->assertNotNull($found, 'rollback could not locate a package migration before this');
        $this->assertStringEndsWith('2026_01_01_000002_pkg_one.php', $found);
    }

    public function test_an_unknown_migration_resolves_to_null(): void
    {
        $this->assertNull($this->resolver->findMigrationFile('2026_01_01_999999_missing'));
    }

    public function test_a_duplicate_registration_is_not_added_twice(): void
    {
        MigrationPathTestProvider::addPath($this->base . '/pkg/migrations');
        MigrationPathTestProvider::addPath($this->base . '/pkg/migrations/');

        $dirs = $this->resolver->migrationDirectories();

        $this->assertSame(count($dirs), count(array_unique($dirs)));
    }
}
