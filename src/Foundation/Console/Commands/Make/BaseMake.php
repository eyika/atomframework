<?php

namespace Eyika\Atom\Framework\Foundation\Console\Commands\Make;

use Exception;
use Eyika\Atom\Framework\Exceptions\Console\BaseConsoleException;
use Eyika\Atom\Framework\Foundation\Console\Command;
use Eyika\Atom\Framework\Support\Storage\Filesystem;

abstract class BaseMake extends Command
{
    protected string $type = '';
    protected string $stub = '';
    
    protected Filesystem $filesystem;

    public function __construct()
    {
        $this->filesystem = new Filesystem;
    }

    public function handle(): bool
    {
        try {
            $name = $this->filename();

            $path = base_path("{$this->directory}/{$name}.php");
    
            if ($this->filesystem->exists($path)) {
                throw new BaseConsoleException("The {$this->type} at '{$path}' already exists");
            }
    
            $content = str_replace('{{name}}', $name, $this->stubContent());
    
            $this->filesystem->put($path, $content);
            $this->info("{$this->type} '{$path}' created successfully at {$this->directory}/");
        } catch (Exception $e) {
            $this->error($e->getMessage());
            return false;
        }

        return true;
    }

    protected function stubContent(): string
    {
        return $this->stub ?: '';
    }

    protected function filename(): string
    {
        return $this->argument(0);
    }
}
