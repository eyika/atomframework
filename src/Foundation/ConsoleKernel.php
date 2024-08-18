<?php

namespace Eyika\Atom\Framework\Foundation;

use Exception;
use Eyika\Atom\Framework\Foundation\Contracts\ConsoleKernel as ContractsConsoleKernel;
use Eyika\Atom\Framework\Support\Facade\Facade;
use Eyika\Atom\Framework\Support\NamespaceHelper;
use Eyika\Atom\Framework\Support\Str;
use Eyika\Atom\Framwork\Foundation\Console\Command;

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
            $this->status = $this->commands[$name]->handle($arguments);
        } else {
            echo "Error: Command '$name' not found." . PHP_EOL;
        }
    }

    public function terminate($inputs = []): int
    {
        return intval($this->status);
    }

    /**
     * Load all the defined commands into console kernel registry
     */
    protected function load()
    {
        try {
            $ds = DIRECTORY_SEPARATOR;
            $fullPath = $GLOBALS['base_path'] . 'app'. $ds. 'Console'. $ds. 'Commands';
            $listObject = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($fullPath, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );
    
            $namespace = NamespaceHelper::getBaseNamespace();
    
            foreach ($listObject as $fileinfo) {
                if (!$fileinfo->isDir() && strtolower(pathinfo($fileinfo->getRealPath(), PATHINFO_EXTENSION)) == explode('.', '.php')[1])
                    $command = classFromFile($fileinfo, $namespace);
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
     * Load all the needed facades into memory
     */
    protected function loadFacades()
    {
        try {
            $ds = DIRECTORY_SEPARATOR;
            $fullPath = base_path() . $ds. "vendor". $ds. "eyika". $ds. "atom-framework". $ds. "src". $ds. "Support". $ds. "Facade";
            $listObject = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($fullPath, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );
    
            $namespace = NamespaceHelper::getBaseNamespace();
            $app = Facade::getFacadeApplication();
    
            foreach ($listObject as $fileinfo) {
                if (!$fileinfo->isDir() && strtolower(pathinfo($fileinfo->getRealPath(), PATHINFO_EXTENSION)) == explode('.', '.php')[1]) {
                    $facade = classFromFile($fileinfo, $namespace);
    
                    $facade_obj = new $namespace."\/$facade";
    
                    $app->instance(Str::camel($facade), $facade_obj);
                }
            }
        } catch (Exception $e) {
            logger()->info("INTERNAL: ".$e->getMessage(), $e->getTrace());
            ///TODO handle exception
        }
    }
}