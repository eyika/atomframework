<?php

namespace Eyika\Atom\Framework\Foundation\Console\Commands\Db;

use Eyika\Atom\Framework\Exceptions\Console\BaseConsoleException;
use Eyika\Atom\Framework\Foundation\Console\Concerns\RunsOnConsole;
use Eyika\Atom\Framework\Foundation\Console\Command;
use Eyika\Atom\Framework\Support\Database\Seeder\DatabaseSeeder;

class Seed extends Command
{
    use RunsOnConsole;

    public string $signature = 'db:seed {--path=} {--class=} {--force=}';

    public function handle(): bool
    {
        try {
            $this->info("Running seeders...");
            (new DatabaseSeeder($this->option('path'), $this->option('class'), $this->option('force')))->run();
            $this->info("Seeding complete!\n");
        } catch (BaseConsoleException $e) {
            $this->error($e->getMessage());
            return !(bool)($e->getCode());
        }
        return true;
    }
}
