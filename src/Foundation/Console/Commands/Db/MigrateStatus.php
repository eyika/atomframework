<?php

namespace Eyika\Atom\Framework\Foundation\Console\Commands\Db;

use Eyika\Atom\Framework\Exceptions\Console\BaseConsoleException;
use Eyika\Atom\Framework\Foundation\Console\Concerns\RunsOnConsole;
use Eyika\Atom\Framework\Foundation\Console\Command;
use Eyika\Atom\Framework\Support\Database\DB;

class MigrateStatus extends Command
{
    use RunsOnConsole;
    use \Eyika\Atom\Framework\Foundation\Console\Concerns\ResolvesMigrationPaths;

    public string $signature = 'migrate:status {--database}';

    public function handle(): bool
    {
        // get('migration') returns ROWS (['migration' => name], …); flatten to a plain list of
        // names so the in_array() below compares string-to-string, not string-to-row (which was
        // always false — every migration showed as not-migrated). Empty table → get() is false.
        $migrated = array_column(DB::table('migrations')->get('migration') ?: [], 'migration');
        // Include package migration directories (ServiceProvider::loadMigrationsFrom), which
        // `migrate` already runs — globbing only the app directory meant a package migration
        // never appeared here at all, migrated or not.
        $migrationFiles = $this->gatherMigrationFiles();

        $this->info("Migration Status:\n");
        $this->table(['Migration', 'Migrated?'], array_map(function ($file) use ($migrated) {
            $name = basename($file, '.php');
            return [$name, in_array($name, $migrated, true) ? '✔️ Yes' : '❌ No'];
        }, $migrationFiles));

        return true;
    }
}
