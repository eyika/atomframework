<?php

namespace Eyika\Atom\Framework\Foundation;

use Exception;
use Eyika\Atom\Framework\Exceptions\NotImplementedException;
use Eyika\Atom\Framework\Foundation\Concerns\ClassDependencyResolver;
use Eyika\Atom\Framework\Foundation\Contracts\ConsoleKernel as ContractsConsoleKernel;
use Eyika\Atom\Framework\Support\Facade\Facade;
use Eyika\Atom\Framework\Support\NamespaceHelper;
use Eyika\Atom\Framework\Foundation\Console\Command;
use Eyika\Atom\Framework\Foundation\Console\Concerns\LogsMessages;
use Eyika\Atom\Framework\Foundation\Console\Contracts\ShouldLogMessages;
use Eyika\Atom\Framework\Foundation\Console\Scheduler;
use Eyika\Atom\Framework\Http\Request;
use Eyika\Atom\Framework\Support\Encrypter;
use Eyika\Atom\Framework\Support\Storage\File;
use Eyika\Atom\Framework\Support\Storage\Storage;

class ConsoleKernel implements ContractsConsoleKernel, ShouldLogMessages
{
    use ClassDependencyResolver, LogsMessages;

    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [];
    protected const ignore_commands = ['BaseMake'];

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
        // $this->loadCommands();
        // $this->loadProjectCommands();
        // // $this->loadLibrariesCommands();
        // $this->loadFacades();

        // Register all providers
        // $this->registerProviders();
        // $this->bootProviders();
    }

    protected $status = false;

    public function register(string $signature, Command|callable $command, array $options = [], $purpose = '')
    {
        $this->commands[$signature] = [ 'command' => $command, 'options' => $options, 'purpose' => $purpose ];
    }

    public function purpose(string $purpose)
    {
        $key = array_key_last($this->commands);
        if ($this->commands[$key]['purpose'] == '')
            $this->commands[$key]['purpose'] = $purpose;
    }

    public function comment(string $comment)
    {
        $this->info($comment);
    }

    public function run(string $signature, array $arguments = [], bool $requireConsoleRoute = true)
    {
        app()->registerProviders();
        //Load console route command definitions into $commands array
        if ($requireConsoleRoute)
            require base_path('routes/console.php');

        if (isset($this->commands[$signature])) {
            $command = $this->commands[$signature]['command'];

            if ($command instanceof Command) {
                $this->status = $command->setAllowedOptions($this->commands[$signature]['options'])
                    ->setArguments($arguments)
                    ->handle();
            } else if (is_callable($command))
                $this->status = $command($arguments);
        } else {
            $this->error("Command '$signature' not found.");
        }
    }

    public function terminate($inputs = []): int
    {
        return intval(!$this->status);
    }

    /**
     * Load all the defined commands into console kernel registry
     */
    public function loadCommands(string|null $fullPath = null, string|null $namespace = null, $base_folder = 'src')
    {
        try {
            $fullPath = $fullPath ?? base_path("vendor/eyika/atom-framework/src/Foundation/Console/Commands");
            $namespace = $namespace ??  framework_namespace();

            NamespaceHelper::loadAndPerformActionOnClasses($namespace, $fullPath, function (string $class_name, string $command) {
                if ($this->shouldIgnoreCommand($command))
                    return;

                $command_obj = $this->resolve($command);

                $args = explode(' ', $command_obj->signature);
                $signature = array_shift($args) ?? strtolower($class_name);
                $this->register($signature, $command_obj, $args, $command_obj instanceof Command ? $command_obj->description : '');
            }, $base_folder);
        } catch (Exception $e) {
            $this->error($e->getMessage(), $e->getTrace());
            $this->error($e->getMessage(), $e->getTrace(), true);
            ///TODO handle exception
        }
    }

    protected function shouldIgnoreCommand(string $commandName)
    {
        foreach ($this::ignore_commands as $command) {
            if (str_contains($commandName, $command))
                return true;
        }
        return false;
    }

    /**
     * Load all the defined project commands into console kernel registry
     */
    public function loadProjectCommands()
    {
        $this->loadCommands(base_path('app/Console/Commands'), project_namespace(), 'app');
    }

    /**
     * Load all the defined third party commands into console kernel registry
     */
    // protected function loadLibrariesCommands()
    // {
    //     throw new NotImplementedException('this method is not yet implemented');
    //     $this->loadCommands(base_path('app/Console/Commands'), project_namespace(), 'app');
    // }

    /**
     * Load all the needed facades into memory
     */
    public function loadFacades()
    {
        try {
            $app = Facade::getFacadeApplication();

            foreach (self::facadables as $tag => $class_name) {
                $facade_obj = new $class_name;
                $app->instance($tag, $facade_obj);
            }
        } catch (Exception $e) {
            $this->error($e->getMessage(), $e->getTrace());
            $this->error($e->getMessage(), $e->getTrace(), true);
            ///TODO handle exception
        }
    }

    public function schedule(): void
    {}
}
