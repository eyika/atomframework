<?php

namespace Eyika\Atom\Framework\Foundation\Console\Commands\Db;

use Eyika\Atom\Framework\Exceptions\Console\BaseConsoleException;
use Eyika\Atom\Framework\Foundation\Console\Concerns\RunsOnConsole;
use Eyika\Atom\Framework\Foundation\Console\Command;
use Eyika\Atom\Framework\Support\Arr;
use Eyika\Atom\Framework\Support\Database\DB;
use Eyika\Atom\Framework\Support\Storage\Filesystem;

class MigrateFresh extends Command
{
    use RunsOnConsole;

    public string $signature = 'migrate:fresh {--seed} {--database}';

    public function handle(): bool
    {
        $status = false;
        try {
            $this->info("Refreshing database...\n");

            $this->dropAllTables();
    
            $status = $this->runMigrations();
    
            $this->info("Database refreshed.\n");
        } catch (BaseConsoleException $e) {
            $this->error($e->getMessage());
            return !(bool)($e->getCode());
        }
        return $status;
    }

    private function runMigrations()
    {
        $migrator = (new Migrate(new Filesystem));
        $migratorOptions = $migrator->allowedOptions;

        foreach ($this->options as $option) {
            if (Arr::exists($migratorOptions, $option)) {
                $migrator->setOption($option, $this->option($option));
            }
        }
        return $migrator->handle();
    }

    private function dropAllTables()
    {
        DB::statement("SET FOREIGN_KEY_CHECKS = 0;");
        $tables = DB::select("SHOW TABLES");

        foreach ($tables as $table) {
            $tableName = array_values((array)$table)[0];
            DB::statement("DROP TABLE IF EXISTS `$tableName`");
        }

        DB::statement("SET FOREIGN_KEY_CHECKS = 1;");
    }
}
