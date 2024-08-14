<?php

namespace Eyika\Atom\Framework\Foundation;

use Eyika\Atom\Framework\Exceptions\NotImplementedException;
use Eyika\Atom\Framework\Foundation\Contracts\ConsoleKernel as ContractsConsoleKernel;
use Eyika\Atom\Framwork\Foundation\Console\Contracts\QueueInterface;
use SplFileInfo;

class ConsoleKernel implements ContractsConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
    ];

    /**
     * Define the application's command schedule.
     */
    public function schedule(QueueInterface $schedule): void
    {
        throw new NotImplementedException('this feature is not yet implemented');
    }

    /**
     * Register the commands for the application.
     */
    public function commands(): void
    {
        throw new NotImplementedException('this feature is not yet implemented');
    }

    /**
     * Register all of the commands in the given directory.
     *
     * @param  array|string  $paths
     * @return void
     */
    // protected function load($paths)
    // {
        // $paths = array_unique(Arr::wrap($paths));

        // $paths = array_filter($paths, function ($path) {
        //     return is_dir($path);
        // });

        // if (empty($paths)) {
        //     return;
        // }

        // $namespace = $this->app->getNamespace();

        // $listObject = new \RecursiveIteratorIterator(
        //     new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
        //     \RecursiveIteratorIterator::CHILD_FIRST
        // );

        // foreach ($listObject as $fileinfo) {
        //     if (!$fileinfo->isDir() && strtolower(pathinfo($fileinfo->getRealPath(), PATHINFO_EXTENSION)) == explode('.', '.php')[1]) {
        //         // $file = basename($fileinfo->getRealPath()); // : $fileinfo->getRealPath();
        //         $command = $this->commandClassFromFile($fileinfo, $namespace);

        //         $this->commands[]

        //         // if (is_subclass_of($command, Command::class) &&
        //         //     ! (new ReflectionClass($command))->isAbstract()) {
        //         //     Artisan::starting(function ($artisan) use ($command) {
        //         //         $artisan->resolve($command);
        //         //     });
        //         // }
        //     }
        // }
    // }

    /**
     * Extract the command class name from the given file path.
     *
     * @param  \SplFileInfo  $file
     * @param  string  $namespace
     * @return string
     */
    // protected function commandClassFromFile(SplFileInfo $file, string $namespace): string
    // {
    //     return $namespace.str_replace(
    //         ['/', '.php'],
    //         ['\\', ''],
    //         Str::after($file->getRealPath(), realpath(base_path()).DIRECTORY_SEPARATOR)
    //     );
    // }
}