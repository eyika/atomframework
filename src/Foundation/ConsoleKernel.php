<?php

namespace Eyika\Atom\Framework\Foundation;

use Exception;
use Eyika\Atom\Framework\Foundation\Contracts\ConsoleKernel as ContractsConsoleKernel;
use Eyika\Atom\Framework\Support\NamespaceHelper;
use Eyika\Atom\Framework\Support\Str;
use Eyika\Atom\Framwork\Foundation\Console\Command;
use SplFileInfo;

class ConsoleKernel implements ContractsConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [];

    protected $status = false;

    public function register(string $name, Command $command)
    {
        $this->commands[$name] = $command;
    }

    public function run(string $name, array $arguments = [])
    {
        if (isset($this->commands[$name])) {
            $this->commands[$name]->handle($arguments);
        } else {
            echo "Error: Command '$name' not found." . PHP_EOL;
        }
    }

    public function terminate($inputs = [])
    {
        return intval($this->status);
    }

    /**
     * Load all the defined commands into console kernel registry
     */
    protected function load()
    {
        try {
            $fullPath = $GLOBALS['base_path'] . 'app/Console/Commands';
            $listObject = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($fullPath, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );
    
            $namespace = NamespaceHelper::getBaseNamespace();
    
            foreach ($listObject as $fileinfo) {
                if (!$fileinfo->isDir() && strtolower(pathinfo($fileinfo->getRealPath(), PATHINFO_EXTENSION)) == explode('.', '.php')[1])
                    $command = $this->commandClassFromFile($fileinfo, $namespace);
                    // $files[] = $basename ? basename($fileinfo->getRealPath()) : $fileinfo->getRealPath();
    
                    $namespace = $namespace."\/$command";
                    $command_obj = new $namespace;
    
                    $this->register($command_obj->signature, $command_obj);
            }
        } catch (Exception $e) {
            logger()->info("INTERNAL: ".$e->getMessage(), $e->getTrace());
            ///TODO handle exception
        }
    }

    /**
     * Extract the command class name from the given file path.
     *
     * @param  \SplFileInfo  $file
     * @param  string  $namespace
     * @return string
     */
    protected function commandClassFromFile(SplFileInfo $file, string $namespace): string
    {
        return $namespace.str_replace(
            ['/', '.php'],
            ['\\', ''],
            Str::after($file->getRealPath(), realpath(base_path()).DIRECTORY_SEPARATOR)
        );
    }
}