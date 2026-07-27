<?php

namespace Eyika\Atom\Framework\Foundation\Console\Commands;

use Eyika\Atom\Framework\Foundation\Console\Command;
use Eyika\Atom\Framework\Support\Config;
use Throwable;

/**
 * Remove the compiled configuration cache file (PERF-10). The next boot globs the
 * config directory again and re-reads env().
 */
class ConfigClear extends Command
{
    public string $signature = 'config:clear';
    public string $description = 'Remove the configuration cache file';

    public function handle(): bool
    {
        try {
            Config::clearCache();
            $this->info('Configuration cache cleared successfully.');
        } catch (Throwable $e) {
            $this->error('Configuration cache clear failed: ' . $e->getMessage());
            return false;
        }

        return true;
    }
}
