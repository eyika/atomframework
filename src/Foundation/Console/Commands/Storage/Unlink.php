<?php

namespace Eyika\Atom\Framework\Foundation\Console\Commands\Storage;

use Exception;
use Eyika\Atom\Framework\Exceptions\Console\BaseConsoleException;
use Eyika\Atom\Framework\Foundation\Console\Command;
use Eyika\Atom\Framework\Support\Facade\File;

class Unlink extends Command
{
    public string $signature = 'storage:unlink';
    public string $description = 'unlink the symlink attached to the storage folder';
    /**
     * Execute the command to create a symbolic link.
     *
     * @throws BaseConsoleException
     */
    public function handle(array $arguments = []): bool
    {
        try {
            $links = config('filesystems.links');

            foreach ($links as $link => $source) {
                $link = File::realpath($link) ?: $link;
        
                if (!file_exists($link)) {
                    continue;
                }

                if (!$this->deleteDirectory($link)) {
                    $this->info("unable to delete [$link]...");
                }
            }
            return true;
        } catch (Exception $e) {
            $this->error($e->getMessage(), $e->getTrace());
            return false;
        }
    }

    protected function deleteDirectory(string $dir): bool
    {
        if (!is_dir($dir)) {
            return false; // Directory doesn't exist
        }

        // Get all files and subdirectories
        $items = scandir($dir);

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue; // Skip the current and parent directory entries
            }

            $path = $dir . DIRECTORY_SEPARATOR . $item;

            // If it's a directory, recursively delete its contents
            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                // If it's a file, delete it
                unlink($path);
            }
        }

        // Finally, delete the main directory
        return rmdir($dir);
    }
}
