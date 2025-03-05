<?php

namespace Database\Seeders;

use Exception;
use Eyika\Atom\Framework\Database\Seeder\Seeder;
use Eyika\Atom\Framework\Foundation\Console\Concerns\LogsMessages;
use Eyika\Atom\Framework\Foundation\Console\Contracts\ShouldLogMessages;

class DatabaseSeeder extends Seeder implements ShouldLogMessages
{
    use LogsMessages;

    /** @property Seeder[] $seeders */
    protected $seeders;
    protected $className;
    protected $path;
    protected $seederPath;

    public function __construct(?string $path, ?string $className, ?string $force)
    {
        $this->className = $className;
        $this->path = $path;
        $this->seederPath = base_path('database/seeds');
        $seeders = glob($this->seederPath . '/*.php');

        foreach ($seeders as $seeder) {
            $className = class_basename($seeder, true);

            if (class_exists($className)) {
                $seeders[] = $className;
            }
        }
    }

    public function run(): bool
    {
        try {
            if (!is_null($this->className)) {
                $seederFile = $this->seederPath . "/{$this->className}.php";
                if (!file_exists($seederFile)) {
                    $this->error("Seeder {$this->className} not found.");
                    return false;
                }
    
                require_once $seederFile;
                if (class_exists($this->className)) {
                    $this->info("Seeding: {$seederFile}");
                    $this->call($this->className);
                    // (new $this->className)->run();
                    $this->info("Seeded: {$seederFile}");
                }
                return true;
            } else if (!is_null($this->path)) {
                $this->path = $this->path;
                if (!file_exists($this->path)) {
                    $this->error("Seeder {$this->path} not found.");
                    return false;
                }
    
                require_once $this->path;
                $className = class_basename($this->path, true);

                if (class_exists($className)) {
                    $this->info("Seeding: {$this->path}");
                    $this->call($className);
                    // (new $options['class'])->run();
                    $this->info("Seeded: {$this->path}");
                }
                return true;
            }
            foreach ($this->seeders as $seeder) {
                $this->call($seeder);
            }
            return true;
        } catch (Exception $e) {
            $this->info($e->getMessage());
            return false;
        }
        // $this->call(UserSeeder::class);
        // $this->call(PostSeeder::class);
    }

    protected function call(string $seederClass)
    {
        $seeder = new $seederClass();
        $seeder->run();
    }
}
