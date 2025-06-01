<?php

namespace Eyika\Atom\Framework\Foundation\Console\Commands\Db;

use Eyika\Atom\Framework\Exceptions\Console\BaseConsoleException;
use Eyika\Atom\Framework\Foundation\Console\Concerns\RunsOnConsole;
use Eyika\Atom\Framework\Foundation\Console\Command;
use Eyika\Atom\Framework\Support\Database\DB;
use Eyika\Atom\Framework\Support\Database\Schema\Migrations\CreateMigrationsTable;
use Eyika\Atom\Framework\Support\Database\Schema\Migrations\Migration;

class Rollback extends Command
{
    use RunsOnConsole;

    public string $signature = 'migrate:rollback {--step=} {--batch=} {--pretend}';

    public function handle(): bool
    {
        ///TODO implement --pretend option handling
        try {
            $this->info("Rolling back migrations...");

            // Ensure migrations table exists
            (new CreateMigrationsTable())->up();
    
            // Get rollback step
            $step = $this->option('step') ?? 0;
            $batch = $this->option('batch');
    
            // for ($i = 0; $i < $step; $i++) {
                // Get latest batch number
                if ($batch && !DB::table('migrations')->where('batch', $batch)->first('batch')) {
                    throw new BaseConsoleException('Invalid batch value.');
                }
                // $batch = $batch ?? DB::table('migrations')->max('batch');

                // Get migrations from the latest batch
                $migration_query = DB::table('migrations');
                if ($batch)
                    $migration_query->where('batch', $batch);
                if ($step > 0) 
                    $migration_query->orderBy('id', "DESC")->limit($step);
                else
                    $migration_query->orderBy('id', "DESC");

                $migrations = $migration_query->get();

                if (!$migrations || count($migrations) < 1) {
                    throw new BaseConsoleException('Nothing to rollback.');
                }
    
                foreach ($migrations as $migration) {
                    $className = $migration['migration'];
                    $file = base_path("database/migrations/{$className}.php");
    
                    if (file_exists($file)) {
                        $migrationObj = require_once $file;
                        if ($migrationObj instanceof Migration || (is_object($migrationObj) && method_exists($migrationObj, 'down'))) {
                            $this->info("Rolling back: $className");
                            $migrationObj->down();

                            // Remove from migrations table
                            DB::table('migrations')->where('migration', $className)->delete();
                            $this->info("Rolled back: $className");
                        } else {
                            throw new BaseConsoleException("Migration $className must return an instance of Eyika\Atom\Framework\Support\Database\Schema\Migrations\Migration or an object that has the methods up and down");
                        }
                    } else {
                        throw new BaseConsoleException("Migration file $file not found");
                    }
                }
            // }
    
            $this->info("Rollback completed.");
        } catch (BaseConsoleException $e) {
            $this->error($e->getMessage());
            return !(bool)($e->getCode());
        }
        return true;
    }
}
