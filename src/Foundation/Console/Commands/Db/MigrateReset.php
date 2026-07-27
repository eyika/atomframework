<?php

namespace Eyika\Atom\Framework\Foundation\Console\Commands\Db;

use Eyika\Atom\Framework\Exceptions\Console\BaseConsoleException;
use Eyika\Atom\Framework\Foundation\Console\Concerns\RunsOnConsole;
use Eyika\Atom\Framework\Foundation\Console\Command;
use Eyika\Atom\Framework\Support\Database\DB;

class MigrateReset extends Command
{
    use RunsOnConsole;

    public string $signature = 'migrate:reset {--database}';

    public function handle(): bool
    {
        $status = false;
        try {
            $this->info("Resetting migrations...\n");

            $status = $this->rollbackAllMigrations();

            $this->info("Migrations have been reset.\n");
        } catch (BaseConsoleException $e) {
            $this->error($e->getMessage());
            return !(bool)($e->getCode());
        }
        return $status;
    }

    private function rollbackAllMigrations()
    {
        $batch = DB::table('migrations')->max('batch');

        while ($batch > 0) {
            $rollback = new Rollback();
            $rollback->handle(['step' => 1]);
            $batch--;
        }

        return true;
    }
}
