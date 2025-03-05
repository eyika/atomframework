<?php

namespace Eyika\Atom\Framework\Foundation\Console\Commands\Db;

use Database\Seeders\DatabaseSeeder;
use Eyika\Atom\Framework\Exceptions\Console\BaseConsoleException;
use Eyika\Atom\Framework\Foundation\Console\Concerns\RunsOnConsole;
use Eyika\Atom\Framework\Foundation\Console\Command;

class Seed extends Command
{
    use RunsOnConsole;

    public string $signature = 'db:seed {--path=} {--class=} {--force=}';

    public function handle(): bool
    {
        try {
            // array_unshift($this->arguments, 'seed:run');

            // $code = $this->executeCommand($this->arguments);
            $this->info("Running seeders...");
            (new DatabaseSeeder($this->option('path'), $this->option('class'), $this->option('force')))->run();
            $this->info("Seeding complete!\n");
        } catch (BaseConsoleException $e) {
            $this->error($e->getMessage());
            return !(bool)($e->getCode());
        }
        return true;
        // return !(bool)$code;
    }
}
