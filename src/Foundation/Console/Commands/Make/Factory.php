<?php

namespace Eyika\Atom\Framework\Foundation\Console\Commands\Make;

class Factory extends BaseMake
{
    public string $description = 'Create a new factory class';

    public string $signature = 'make:factory';
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
