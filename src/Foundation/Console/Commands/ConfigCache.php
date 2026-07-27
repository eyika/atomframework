<?php

namespace Eyika\Atom\Framework\Foundation\Console\Commands;

use Eyika\Atom\Framework\Foundation\Console\Command;
use Eyika\Atom\Framework\Support\Config;
use Throwable;

/**
 * Compile all config files into a single cached PHP file (PERF-10). Subsequent boots
 * load the merged config in one require instead of globbing + requiring every file.
 */
class ConfigCache extends Command
{
    public string $signature = 'config:cache';
    public string $description = 'Create a cache file for faster configuration loading';

    public function handle(): bool
    {
        try {
            // Always rebuild from source: drop any stale cache first, then compile.
            Config::clearCache();
            $path = Config::cache();
            $this->info("Configuration cached successfully: $path");
        } catch (Throwable $e) {
            $this->error('Configuration cache failed: ' . $e->getMessage());
            return false;
        }

        return true;
    }
}
