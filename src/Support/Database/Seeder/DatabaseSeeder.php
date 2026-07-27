<?php

namespace Eyika\Atom\Framework\Support\Database\Seeder;

use Exception;
use Eyika\Atom\Framework\Foundation\Console\Concerns\LogsMessages;
use Eyika\Atom\Framework\Foundation\Console\Contracts\ShouldLogMessages;

class DatabaseSeeder extends Seeder implements ShouldLogMessages
{
    use LogsMessages;

    /** @property Seeder[] $seeders */
    protected $seeders = [];
    protected $className;
    protected $path;
    protected $seederPath;

    public function __construct(?string $path, ?string $className, ?string $force)
    {
        $this->className = $className;
        $this->path = $path;
        $this->seederPath = base_path('database/seeds');
    }

    public function run(): bool
    {
        try {
            if (!is_null($this->className)) {
                $seederFile = $this->seederPath . "/{$this->className}.php";
                $this->info("Seeding: {$this->className}");
                if (!file_exists($seederFile)) {
                    $this->error("Seeder {$this->className} not found.");
                    return false;
                }

                $className = database_namespace('Seeds\\'.class_basename($seederFile, true));

                if (!class_exists($className)) {
                    $this->info("Could Not Seed: {$className}");
                    return false;
                }
                $this->call($className);
                $this->info("Seeded: {$this->path}");
                return true;
            } else if (!is_null($this->path)) {
                if (!file_exists($this->path)) {
                    $this->error("Seeder {$this->path} not found.");
                    return false;
                }
    
                $className = database_namespace('Seeds\\'.class_basename($this->path, true));
                $this->info("Seeding: {$className}");

                if (!class_exists($className)) {
                    $this->info("Could Not Seed: {$className}");
                    return false;
                }
                $this->call($className);
                $this->info("Seeded: {$className}");
                return true;
            }

            $seeders = glob($this->seederPath . '/*.php');
            foreach ($seeders as $seeder) {
                $className = database_namespace('Seeds\\'.class_basename($seeder, true));
                $this->info("Seeding: {$className}");

                if (!class_exists($className)) {
                    $this->info("Could Not Seed: {$className}");
                    continue;
                }
                $this->call($className);
                $this->info("Seeded: {$className}");
            }

            return true;
        } catch (Exception $e) {
            $this->info($e->getMessage());
            return false;
        }
    }

    protected function call(string $seederClass)
    {
        $seeder = new $seederClass();
        $seeder->run();
    }
}
