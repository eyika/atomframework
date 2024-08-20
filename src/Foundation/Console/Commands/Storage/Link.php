<?php

namespace Eyika\Atom\Framework\Foundation\Console\Commands\Storage;

use Exception;
use Eyika\Atom\Framework\Exceptions\Console\BaseConsoleException;
use Eyika\Atom\Framework\Foundation\Console\Command;
use Eyika\Atom\Framework\Support\Storage\File;

class Link extends Command
{
    public string $signature = 'storage:link';
    public string $description = 'link the storage folder to the public folder';
    /**
     * Execute the command to create a symbolic link.
     *
     * @throws BaseConsoleException
     */
    public function handle(array $arguments = []): bool
    {
        try {
            $publicPath = public_path('storage'); $storagePath = storage_path('app/public');
            $publicPath = File::realpath($publicPath) ?: $publicPath;
            $storagePath = File::realpath($storagePath) ?: $storagePath;
    
            if (File::exists($publicPath)) {
                throw new BaseConsoleException("The \"$publicPath\" directory already exists.");
            }
    
            if (!File::exists($storagePath)) {
                File::makeDirectory($storagePath, 0755, true);
                $this->info("The \"$storagePath\" directory does not exist, creating it...");
            }
    
            if (File::symlink($storagePath, $publicPath)) {
                $this->info("The [$publicPath] directory has been linked.\n");
            } else {
                throw new BaseConsoleException("Failed to create the symbolic link.");
            }
            return true;
        } catch (Exception $e) {
            $this->error($e->getMessage(), $e->getTrace());
            return false;
        }
    }
}
