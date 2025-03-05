<?php

namespace Atom\Framework\Console\Commands;

use Atom\Framework\Console\GeneratorCommand;

class Factory extends GeneratorCommand
{
    protected string $name = 'make:factory';
    protected string $description = 'Create a new factory class';
    protected string $type = 'Factory';
    protected string $directory = 'database/factories';

    protected string $stub = <<<EOT
<?php

namespace Database\Factories;

use Atom\Framework\Database\Factories\Factory;

class {{name}} extends Factory
{
    public function definition(): array
    {
        return [
            // Define default attributes here
        ];
    }

    protected function getTable(): string
    {
        return 'table_name';
    }
}

EOT;
}
