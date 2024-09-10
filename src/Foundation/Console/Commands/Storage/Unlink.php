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
            $links = config('filesystem.links');

            foreach ($links as $link => $source) {
                $link = File::realpath($link) ?: $link;
        
                if (!File::exists($link)) {
                    continue;
                }

                if (!File::deleteDirectory($link)) {
                    $this->info("unable to delete [$link]...");
                }
            }
            return true;
        } catch (Exception $e) {
            $this->error($e->getMessage(), $e->getTrace());
            return false;
        }
    }
}
