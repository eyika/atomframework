<?php

namespace Eyika\Atom\Framework\Foundation\Console\Commands;

use Eyika\Atom\Framework\Foundation\Console\Command;
use Eyika\Atom\Framework\Http\Route;
use Throwable;

/**
 * Compile each closure-free route file into a fast cache (PERF-11). At boot the
 * cached table is loaded in one require instead of re-executing the route file's
 * registration calls. A file that contains closure routes can't be var_export'd, so
 * it is skipped (and any stale cache removed) — its closures stay dynamic.
 */
class RouteCache extends Command
{
    public string $signature = 'route:cache';
    public string $description = 'Create a route cache file for faster route registration';

    public function handle(): bool
    {
        $files = [
            'web' => base_path('routes/web.php'),
            'api' => base_path('routes/api.php'),
        ];

        $cachedAny = false;

        foreach ($files as $name => $source) {
            if (!is_file($source)) {
                continue;
            }

            $path = Route::routeCachePath($name);
            if ($path === null) {
                $this->error('Cannot resolve the route cache path (base_path unavailable).');
                return false;
            }

            try {
                $data = Route::buildRouteCacheData($source);
            } catch (Throwable $e) {
                $this->error("Failed to load '$name' routes: " . $e->getMessage());
                return false;
            }

            if (!empty($data['closures'])) {
                // A stale cache would otherwise mask the (now closure-bearing) file.
                if (is_file($path)) {
                    @unlink($path);
                }
                $this->warn("Skipped '$name' route cache — closure routes can't be cached: " . implode('; ', $data['closures']));
                $this->info("  Convert those to controller actions to enable caching for '$name'.");
                continue;
            }

            $dir = dirname($path);
            if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
                $this->error("Unable to create route cache directory: $dir");
                return false;
            }

            file_put_contents($path, "<?php\n\nreturn " . var_export($data['routes'], true) . ";\n", LOCK_EX);
            $this->info("Cached '$name' routes: $path");
            $cachedAny = true;
        }

        if (!$cachedAny) {
            $this->info('No closure-free route files were cached.');
        }

        return true;
    }
}
