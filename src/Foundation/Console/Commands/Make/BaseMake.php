<?php

namespace Eyika\Atom\Framework\Foundation\Console\Commands\Make;

use Eyika\Atom\Framework\Foundation\Console\Command;

abstract class BaseMake extends Command
{
    protected string $type = '';
    protected string $stub = '';

    public function handle(): bool
    {
        $name = $this->filename();
        if (!$name) {
            $this->error("Please provide a name for the {$this->type}.");
            return false;
        }

        $path = base_path("{{$this->directory}/{$name}.php");

        if (file_exists($path)) {
            $this->error("The {$this->type} '{$name}' already exists.");
            return false;
        }

        $content = str_replace('{{name}}', $name, $this->stubContent());
        file_put_contents($path, $content);
        $this->info("{$this->type} '{$name}' created successfully at {$this->directory}/");

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
