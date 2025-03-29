<?php

namespace Eyika\Atom\Framework\Foundation\Console\Commands\Vendor;

use Eyika\Atom\Framework\Exceptions\Console\BaseConsoleException;
use Eyika\Atom\Framework\Foundation\Console\Command;
use Eyika\Atom\Framework\Foundation\ServiceProvider;

class Publish extends Command
{
    public string $signature = 'vendor:publish {--tag=} {--provider=} {--force}';

    public string $description = 'Publish all registered assets and configurations';

    public function handle(): bool
    {
        try {
            $tag = $this->option('tag'); // Allow publishing by tag
            $provider = $this->option('provider');
            $force = $this->option('force');
    
            // $tag = $this->argument('tag');

            ServiceProvider::publishAll($tag, $provider, $force);
            $this->info($tag ? "Published assets for tag: $tag" : "Published all assets.");
        } catch (BaseConsoleException $e) {
            $this->error($e->getMessage());
            return !(bool)($e->getCode());
        }
        return true;
    }
}
