<?php

namespace Eyika\Atom\Framework\Foundation;

use Exception;
use Eyika\Atom\Framework\Foundation\Contracts\ConsoleKernel as ContractsConsoleKernel;
use Eyika\Atom\Framework\Support\Facade\Facade;
use Eyika\Atom\Framework\Support\NamespaceHelper;
use Eyika\Atom\Framework\Support\Str;
use Eyika\Atom\Framework\Foundation\Console\Command;
use Eyika\Atom\Framework\Support\Encrypter;
use Eyika\Atom\Framework\Support\Facade\Request;
use Eyika\Atom\Framework\Support\Facade\Scheduler;
use Eyika\Atom\Framework\Support\Storage\File;
use Eyika\Atom\Framework\Support\Storage\Storage;

class ConsoleKernel implements ContractsConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [];

    protected const ignore_facades = ['app', 'application'];
    protected const facadables = [
        'file' => File::class,
        'storage' => Storage::class,
        'encrypter' => Encrypter::class,
        'request' => Request::class,
        'scheduler' => Scheduler::class,
    ];

    public function __construct()
    {
        $this->loadCommands();
        $this->loadThirdPartyCommands();
        $this->loadFacades();
    }

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
            $fullPath = $fullPath ?? base_path("vendor/eyika/atom-framework/src/Foundation/Console/Commands");
            $namespace = framework_namespace();

            NamespaceHelper::loadAndPerformActionOnClasses($namespace, $fullPath, function (string $class_name, string $command) {
                $command_obj = new $command;
    
                $args = explode(' ', $command_obj->signature);
                $signature = array_shift($args) ?? '';
                $this->register($signature, $command_obj, $args);
            });
            // $listObject = new \RecursiveIteratorIterator(
            //     new \RecursiveDirectoryIterator($fullPath, \RecursiveDirectoryIterator::SKIP_DOTS),
            //     \RecursiveIteratorIterator::CHILD_FIRST
            // );
            // $namespace = NamespaceHelper::getBaseNamespace();

            // foreach ($listObject as $fileinfo) {
            //     if (!$fileinfo->isDir() && strtolower(pathinfo($fileinfo->getRealPath(), PATHINFO_EXTENSION)) == explode('.', '.php')[1])
            //         $command = classFromFile($fileinfo, $namespace);
            //         $command_obj = new $command;
    
            //         $args = explode(' ', $command_obj->signature);
            //         $signature = array_shift($args) ?? '';
            //         $this->register($signature, $command_obj, $args);
            // }
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
            // $fullPath = base_path("vendor/eyika/atom-framework/src/Support/Facade");
            // $namespace = framework_namespace();
            $app = Facade::getFacadeApplication();

            // NamespaceHelper::loadAndPerformActionOnClasses($namespace, $fullPath, function (string $class_name, string $facade) use ($app) {
            //     if (in_array(strtolower($class_name), static::ignore_facades))
            //         return false;

            //     $facade_obj = new $facade;

            //     $app->instance(Str::camel($class_name), $facade_obj);
            // });

            $facades = self::facadables;

            foreach ($facades as $tag => $class_name) {
                $facade_obj = new $class_name;
                $app->instance($tag, $facade_obj);
            }
            // $listObject = new \RecursiveIteratorIterator(
            //     new \RecursiveDirectoryIterator($fullPath, \RecursiveDirectoryIterator::SKIP_DOTS),
            //     \RecursiveIteratorIterator::CHILD_FIRST
            // );
    
            // $namespace = NamespaceHelper::getBaseNamespace();
    
            // foreach ($listObject as $fileinfo) {
            //     if (!$fileinfo->isDir() && strtolower(pathinfo($fileinfo->getRealPath(), PATHINFO_EXTENSION)) == explode('.', '.php')[1]) {
            //         $facade = classFromFile($fileinfo, $namespace);
    
            //         $facade_obj = new $namespace."\/$facade";
    
            //         $app->instance(Str::camel($facade), $facade_obj);
            //     }
            // }
        } catch (Exception $e) {
            logger()->info("INTERNAL: ".$e->getMessage(), $e->getTrace());
            ///TODO handle exception
        }
    }
}