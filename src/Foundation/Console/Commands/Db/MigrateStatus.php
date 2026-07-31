<?php

namespace Eyika\Atom\Framework\Foundation\Console\Commands\Db;

use Eyika\Atom\Framework\Exceptions\Console\BaseConsoleException;
use Eyika\Atom\Framework\Foundation\Console\Concerns\RunsOnConsole;
use Eyika\Atom\Framework\Foundation\Console\Command;
use Eyika\Atom\Framework\Support\Database\DB;

class MigrateStatus extends Command
{
    use RunsOnConsole;

    public string $signature = 'migrate:status {--database}';

    public function handle(): bool
    {
        // get('migration') returns ROWS (['migration' => name], …); flatten to a plain list of
        // names so the in_array() below compares string-to-string, not string-to-row (which was
        // always false — every migration showed as not-migrated). Empty table → get() is false.
        $migrated = array_column(DB::table('migrations')->get('migration') ?: [], 'migration');
        $migrationFiles = glob(database_path('migrations/*.php'));

        $this->info("Migration Status:\n");
        $this->table(['Migration', 'Migrated?'], array_map(function ($file) use ($migrated) {
            $name = basename($file, '.php');
            return [$name, in_array($name, $migrated, true) ? '✔️ Yes' : '❌ No'];
        }, $migrationFiles));

        return true;
    }
}
