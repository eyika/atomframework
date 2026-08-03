<?php

namespace Eyika\Atom\Framework\Foundation\Console\Commands\Db;

use Exception;
use Eyika\Atom\Framework\Exceptions\Console\BaseConsoleException;
use Eyika\Atom\Framework\Foundation\Console\Concerns\RunsOnConsole;
use Eyika\Atom\Framework\Foundation\Console\Command;
use Eyika\Atom\Framework\Foundation\ServiceProvider;
use Eyika\Atom\Framework\Support\Database\DB;
use Eyika\Atom\Framework\Support\Database\Schema\Migrations\CreateMigrationsTable;
use Eyika\Atom\Framework\Support\Database\Schema\Migrations\Migration;
use Eyika\Atom\Framework\Support\Storage\Filesystem;

class Migrate extends Command
{
    use RunsOnConsole;
    use \Eyika\Atom\Framework\Foundation\Console\Concerns\ResolvesMigrationPaths;

    private Filesystem $filesystem;

    public string $signature = 'migrate {--seed} {--path=} {--basepath=} {--pretend}';

    public function __construct()
    {
        parent::__construct(); // initialize base option/argument state
        $this->filesystem = new Filesystem;
    }

    public function handle(): bool
    {
        try {
            $pretend = (bool) $this->option('pretend');
            $this->info($pretend ? "Pretend mode — no migrations will run." : "Running migrations...");

            // Get migrations path (supports custom path)
            if (($path = $this->option('path')) && $this->filesystem->missing($path)) {
                throw new BaseConsoleException("specified migration file path not found");
            }

            $migrationPath = $options['basepath'] ?? base_path('database/migrations');
            // Include package migration directories registered via
            // ServiceProvider::loadMigrationsFrom() (PKG-01) alongside the app's.
            $migrations = $path ? [$path] : $this->gatherMigrations($migrationPath);

            // --pretend: list the migrations that WOULD run, in order, then return without
            // touching the database — no CreateMigrationsTable, no up(), no inserts. If the
            // migrations table doesn't exist yet, every gathered migration would run.
            if ($pretend) {
                $already = [];
                try {
                    $already = array_column(DB::table('migrations')->get('migration') ?: [], 'migration');
                } catch (\Throwable $e) {
                    // migrations table not created yet — nothing has run
                }
                $pending = 0;
                foreach ($migrations as $migration) {
                    $filename = pathinfo($migration, PATHINFO_FILENAME);
                    if (in_array($filename, $already, true)) {
                        continue;
                    }
                    $this->info("  would migrate: $filename");
                    $pending++;
                }
                $this->info($pending === 0 ? "Nothing to migrate." : "$pending migration(s) would run.");
                return true;
            }

            // Ensure migrations table exists
            (new CreateMigrationsTable())->up();

            // Get last batch number
            $lastBatch = DB::table('migrations')->max('batch') ?? 0;
            $batch = $lastBatch + 1;
    
            foreach ($migrations as $migration) {
                $filename = pathinfo($migration, PATHINFO_FILENAME);
                $migrationObj = require_once $migration;
    
                // Check if migration has already been executed
                $exists = DB::table('migrations')->where('migration', $filename)->exists();
                if ($exists) {
                    $this->info("Skipped: $filename (already migrated)");
                    continue;
                }
    
                if ($migrationObj instanceof Migration || (is_object($migrationObj) && method_exists($migrationObj, 'up'))) {
                    $this->info("Migrating: $filename");
                    $migrationObj->up();
    
                    // Store migration record
                    DB::table('migrations')->insert([
                        'migration' => $filename,
                        'batch' => $batch,
                    ]);
    
                    $this->info("Migrated: $filename");
                }
            }

            if ($this->option('seed')) {
                $this->call('db:seed');
            }
    
            $this->info("Migrations completed.");
        } catch (BaseConsoleException $e) {
            $this->error($e->getMessage());
            return false;
        }
        return true;
    }

    /**
     * Collect migration files from the app's directory plus every package directory registered
     * via ServiceProvider::loadMigrationsFrom() (PKG-01). Shared with rollback/status through
     * ResolvesMigrationPaths so all the migrate commands agree on where migrations live.
     *
     * @return string[]
     */
    private function gatherMigrations(string $basePath): array
    {
        return $this->gatherMigrationFiles($basePath);
    }
}
