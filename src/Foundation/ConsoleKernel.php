<?php

namespace Eyika\Atom\Framework\Foundation;

use Exception;
use Eyika\Atom\Framework\Foundation\Contracts\ConsoleKernel as ContractsConsoleKernel;
use Eyika\Atom\Framework\Support\Facade\Facade;
use Eyika\Atom\Framework\Support\NamespaceHelper;
use Eyika\Atom\Framework\Support\Str;
use Eyika\Atom\Framework\Foundation\Console\Command;

class ConsoleKernel implements ContractsConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [];

    protected $status = false;

    public function register(string $name, Command|callable $command, array $options = [])
    {
        $this->commands[$name] = [ 'command' => $command, 'options' => $options, 'purpose' => '' ];
    }

    public function purpose(string $purpose)
    {
        $key = array_key_last($this->commands);
        if ($this->commands[$key]['purpose'] == '')
            $this->commands[$key]['purpose'] = $purpose;
    }

    public function comment(string $comment)
    {
        consoleLog(0, "Info: $comment." . PHP_EOL);
    }

    public function run(string $name, array $arguments = [])
    {
        //Load console route command definitions into $commands array
        require base_path().'/routes/console.php';

        if (isset($this->commands[$name])) {
            $command = $this->commands[$name]['command'];

            if ($command instanceof Command) {
                $this->commands[$name]['command']->setAllowedOptions($this->commands[$name]['options']);
                $this->status = $command->handle($arguments);
            } else if (is_callable($command))
                $this->status = $command($arguments);
        } else {
            consoleLog(1, "Error: Command '$name' not found." . PHP_EOL);
        }
    }

    public function terminate($inputs = []): int
    {
        return intval(!$this->status);
    }

    /**
     * Load all the defined commands into console kernel registry
     */
    protected function loadCommands(string $fullPath = null)
    {
        try {
            $ds = DIRECTORY_SEPARATOR;
            $fullPath = $fullPath ?? base_path("vendor/eyika/atom-framework/src/Foundation/Console/Commands");
            $listObject = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($fullPath, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );
            $namespace = NamespaceHelper::getBaseNamespace();

            foreach ($listObject as $fileinfo) {
                if (!$fileinfo->isDir() && strtolower(pathinfo($fileinfo->getRealPath(), PATHINFO_EXTENSION)) == explode('.', '.php')[1])
                    $command = classFromFile($fileinfo, $namespace);
                    $command_obj = new $command;
    
                    $args = explode(' ', $command_obj->signature);
                    $signature = array_shift($args) ?? '';
                    $this->register($signature, $command_obj, $args);
            }
        } catch (Exception $e) {
            logger()->info("INTERNAL: ".$e->getMessage(), $e->getTrace());
            ///TODO handle exception
        }
    }

    /**
     * Load all the defined third party commands into console kernel registry
     */
    protected function loadThirdPartyCommands()
    {
        $this->loadCommands(base_path() . 'app/Console/Commands');
    }

    /**
     * Load all the needed facades into memory
     */
    protected function loadFacades()
    {
        try {
            $ds = DIRECTORY_SEPARATOR;
            $fullPath = base_path("vendor/eyika/atom-framework/src/Support/Facade");
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