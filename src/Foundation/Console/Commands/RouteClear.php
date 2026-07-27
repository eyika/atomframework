<?php

namespace Eyika\Atom\Framework\Foundation\Console\Commands;

use Eyika\Atom\Framework\Foundation\Console\Command;
use Eyika\Atom\Framework\Http\Route;

/**
 * Remove the compiled route cache files (PERF-11). The next boot requires the route
 * source files again.
 */
class RouteClear extends Command
{
    public string $signature = 'route:clear';
    public string $description = 'Remove the route cache files';

    public function handle(): bool
    {
        $removed = 0;
        foreach (['web', 'api'] as $name) {
            $path = Route::routeCachePath($name);
            if ($path !== null && is_file($path)) {
                @unlink($path);
                $this->info("Removed: $path");
                $removed++;
            }
        }

        $this->info($removed > 0 ? 'Route cache cleared.' : 'No route cache to clear.');

        return true;
    }
}
