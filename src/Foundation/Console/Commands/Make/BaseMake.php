<?php

namespace Eyika\Atom\Framework\Foundation\Console\Commands\Make;

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
        $name = $this->filename();
        if (!$name) {
            $this->error("Please provide a name for the {$this->type}.");
            return false;
        }

        $path = base_path("{$this->directory}/{$name}.php");

        if ($this->filesystem->exists($path)) {
            $this->error("The {$this->type} at '{$path}' already exists.");
            return false;
        }

        $content = str_replace('{{name}}', $name, $this->stubContent());

        $this->filesystem->put($path, $content);
        $this->info("{$this->type} '{$path}' created successfully at {$this->directory}/");

        return true;
    }

    protected function stubContent(): string
    {
        return $this->stub ?: '';
    }

    protected function filename(): null|string
    {
        return $this->argument(0);
    }
}
