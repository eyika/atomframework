<?php

namespace Eyika\Atom\Framework\Foundation\Console\Commands\Db;

use Eyika\Atom\Framework\Exceptions\Console\BaseConsoleException;
use Eyika\Atom\Framework\Foundation\Console\Concerns\RunsOnConsole;
use Eyika\Atom\Framework\Foundation\Console\Command;
use Eyika\Atom\Framework\Support\Arr;
use Eyika\Atom\Framework\Support\Storage\Filesystem;

class MigrateRefresh extends Command
{
    use RunsOnConsole;

    public string $signature = 'migrate:refresh {--seed} {--database} {--step=}';

    public function handle(): bool
    {
        $status = false;
        try {
            $this->info("Refreshing migrations...\n");

            // Rollback migrations
            $status = $this->rollbackMigrations();

            // Run migrations again
            $status = $this->runMigrations();

            $this->info("Migrations refreshed.\n");
        } catch (BaseConsoleException $e) {
            $this->error($e->getMessage());
            return !(bool)($e->getCode());
        }
        return $status;
    }

    private function rollbackMigrations()
    {
        $rollback = new Rollback();
        $rollbackOptions = $rollback->allowedOptions;
        
        foreach ($this->options as $option) {
            if (Arr::exists($rollbackOptions, $option) && $this->option($option)) {
                $rollback->setOption($option, $this->option($option));
            }
        }
        return $rollback->handle();
    }

    private function runMigrations()
    {
        $migrator = new Migrate(new Filesystem);
        $migratorOptions = $migrator->allowedOptions;

        foreach ($this->options as $option) {
            if (Arr::exists($migratorOptions, $option) && $this->option($option)) {
                $migrator->setOption($option, $this->option($option));
            }
        }
        return $migrator->handle();
    }
}
