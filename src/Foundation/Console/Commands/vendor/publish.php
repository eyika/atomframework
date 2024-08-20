<?php

namespace Eyika\Atom\Framework\Foundation\Console\Commands\Vendor;

use Eyika\Atom\Framework\Exceptions\Console\BaseConsoleException;
use Eyika\Atom\Framework\Foundation\Console\Command;
use Eyika\Atom\Framework\Support\Storage\File;

class Publish extends Command
{
    public string $signature = 'vendor:publish {--tag=} {--provider=} {--force}';

    public string $description = 'implementation of vendor:publish command';

    public function handle(array $arguments = []): bool
    {
        try {
            $tag = $this->option('tag');
            $provider = $this->option('provider');
            $force = $this->option('force');
    
            // paths where files are stored in the package
            $paths = [
                'config' => config_path(),
                'views' => resource_path('views/vendor'),
                'migrations' => database_path('migrations'),
                'translations' => resource_path('lang/vendor'),
                'assets' => public_path('vendor'),
            ];
    
            $vendorPath = base_path('vendor/eyika/atom');   //TODO: implement a way to discover all third party atom packages and implement their vendorPaths for publishing
    
            // Files or directories to publish based on the tag or provider
            $publishables = [
                'config' => "$vendorPath/config/package-config.php",
                'views' => "$vendorPath/resources/views",
                'migrations' => "$vendorPath/database/migrations",
                'translations' => "$vendorPath/resources/lang",
                'assets' => "$vendorPath/public",
            ];
    
            foreach ($publishables as $type => $source) {
                // Check if the type matches the given tag or no tag is specified
                if (!$tag || in_array($tag, [ 'all', $type ])) {
                    $destination = $paths[$type];
    
                    // Publish the files (copy them to the destination)
                    $this->publishFiles($source, $destination, $force);
                }
            }
    
            $this->info('Files published successfully.');
        } catch (BaseConsoleException $e) {
            $this->error($e->getMessage());
            return !(bool)($e->getCode());
        }
        return true;
    }

    protected function publishFiles($source, $destination, $force = false)
    {
        if (!File::exists($source)) {
            $this->error("Source path does not exist: $source");
            return;
        }

        if (File::isDirectory($source)) {
            File::copyDirectory($source, $destination);
        } else {
            if (File::exists($destination) && !$force) {
                $this->warn("File already exists: $destination");
            } else {
                File::copy($source, $destination);
            }
        }

        $this->info("Published: $source to $destination");
    }
}
