<?php

namespace Eyika\Atom\Framework\Foundation\Console\Commands\Storage;

use Exception;
use Eyika\Atom\Framework\Exceptions\Console\BaseConsoleException;
use Eyika\Atom\Framework\Foundation\Console\Command;
use Eyika\Atom\Framework\Support\Facade\File;
use League\Flysystem\Visibility;

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
            $links = config('filesystems.links');

            foreach ($links as $link => $source) {
                $link = File::realpath($link) ?: $link;
                $source = File::realpath($source) ?: $source;
                
                if (file_exists($link)) {
                    throw new BaseConsoleException("The [$link] directory already exists.");
                }
        
                if (!file_exists($source)) {
                    mkdir($source, 0755, true);
                    $this->info("The [$source] directory does not exist, creating it...");
                }

                $_link = explode(DIRECTORY_SEPARATOR, $link);
                array_pop($_link);
                $_link = implode(DIRECTORY_SEPARATOR, $_link);

                if (!file_exists($_link)) {
                    mkdir($_link, 0777, true);
                    $this->info("The directory [$_link] does not exist, creating it...");
                }
        
                if (File::symlink($source, $link)) {
                    $this->info("The [$link] directory has been linked.\n");
                } else {
                    throw new BaseConsoleException("Failed to create the symbolic link.");
                }
            }
            return true;
        } catch (Exception $e) {
            $this->error($e->getMessage(), $e->getTrace());
            return false;
        }
    }
}
