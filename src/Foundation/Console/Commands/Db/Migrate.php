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

    private Filesystem $filesystem;

    public string $signature = 'migrate {--seed} {--path=} {--basepath=} {--pretend} {--force}';

    public function __construct()
    {
        $this->filesystem = new Filesystem;
    }

    public function handle(): bool
    {
        ///TODO implement --pretend and --force option handling
        try {
            $this->info("Running migrations...");

            // Get migrations path (supports custom path)
            if (($path = $this->option('path')) && $this->filesystem->missing($path)) {
                throw new BaseConsoleException("specified migration file path not found");
            }

            $migrationPath = $options['basepath'] ?? base_path('database/migrations');
            // Include package migration directories registered via
            // ServiceProvider::loadMigrationsFrom() (PKG-01) alongside the app's.
            $migrations = $path ? [$path] : $this->gatherMigrations($migrationPath);
    
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
     * Collect migration files from the app's directory plus every package directory
     * registered via ServiceProvider::loadMigrationsFrom() (PKG-01). Each directory
     * is sorted by filename; the app's run first, then packages in registration order.
     *
     * @return string[]
     */
    private function gatherMigrations(string $basePath): array
    {
        $files = glob($basePath . '/*.php') ?: [];

        foreach (ServiceProvider::migrationPaths() as $packagePath) {
            $files = array_merge($files, glob(rtrim($packagePath, '/\\') . '/*.php') ?: []);
        }

        return $files;
    }
}
