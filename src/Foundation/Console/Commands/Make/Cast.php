<?php

namespace Eyika\Atom\Framework\Foundation\Console\Commands\Make;

class Cast extends BaseMake
{
    public string $signature = 'make:cast';
    protected string $type = 'Cast';
    protected string $directory = 'app/Casts';
    protected string $stub = <<<'EOT'
<?php

namespace App\Casts;

class {{name}}
{
    /**
     * Cast the stored value when reading from the model.
     */
    public function get(mixed $value): mixed
    {
        return $value;
    }

    /**
     * Prepare the value for storage when writing to the model.
     */
    public function set(mixed $value): mixed
    {
        return $value;
    }
}
EOT;
}
