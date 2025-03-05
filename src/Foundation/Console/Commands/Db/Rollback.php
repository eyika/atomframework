<?php

namespace Eyika\Atom\Framework\Foundation\Console\Commands\Db;

use Eyika\Atom\Framework\Exceptions\Console\BaseConsoleException;
use Eyika\Atom\Framework\Foundation\Console\Concerns\RunsOnConsole;
use Eyika\Atom\Framework\Foundation\Console\Command;
use Eyika\Atom\Framework\Support\Database\DB;
use Eyika\Atom\Framework\Support\Database\Schema\Migrations\CreateMigrationsTable;

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
            $step = $this->option('step') ?? 1;
    
            for ($i = 0; $i < $step; $i++) {
                // Get latest batch number
                if (($batch = $this->option('batch')) && !DB::table('migrations')->where('batch', $batch)->first('batch')) {
                    $this->info("Invalid batch value.");
                    return false;
                }
                $batch = $batch ?? DB::table('migrations')->max('batch');
                if (!$batch) {
                    $this->info("Nothing to rollback.");
                    return false;
                }
    
                // Get migrations from the latest batch
                $migrations = DB::table('migrations')->where('batch', $batch)->get();
    
                foreach ($migrations as $migration) {
                    $className = $migration->migration;
                    $file = base_path("database/migrations/{$className}.php");
    
                    if (file_exists($file)) {
                        require_once $file;
                        if (class_exists($className)) {
                            $this->info("Rolling back: $className");
                            (new $className)->down();
    
                            // Remove from migrations table
                            DB::table('migrations')->where('migration', $className)->delete();
                            $this->info("Rolled back: $className");
                        }
                    }
                }
            }
    
            $this->info("Rollback completed.");
        } catch (BaseConsoleException $e) {
            $this->error($e->getMessage());
            return !(bool)($e->getCode());
        }
        return !(bool)$code;
    }
}
