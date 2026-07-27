<?php

namespace Eyika\Atom\Framework\Foundation\Console\Commands;

use Eyika\Atom\Framework\Foundation\Console\Command;
use Eyika\Atom\Framework\Foundation\PackageManifest;
use Throwable;

/**
 * Rebuild the cached package manifest (PKG-02) from installed.json so auto-discovered
 * providers/aliases load without re-parsing composer's metadata each request. Run on
 * deploy / after composer install|update.
 */
class PackageDiscover extends Command
{
    public string $signature = 'package:discover';
    public string $description = 'Rebuild the cached package manifest';

    public function handle(): bool
    {
        try {
            $manifest = new PackageManifest();
            $compiled = $manifest->writeManifest();

            $providers = 0;
            foreach ($compiled as $package) {
                $providers += count($package['providers'] ?? []);
            }

            $this->info(sprintf(
                'Discovered %d package(s), %d provider(s): %s',
                count($compiled),
                $providers,
                $manifest->manifestPath()
            ));

            foreach (array_keys($compiled) as $name) {
                $this->info("  - $name");
            }
        } catch (Throwable $e) {
            $this->error('Package discovery failed: ' . $e->getMessage());
            return false;
        }

        return true;
    }
}
