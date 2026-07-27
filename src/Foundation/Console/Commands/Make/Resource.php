<?php

namespace Eyika\Atom\Framework\Foundation\Console\Commands\Make;

class Resource extends BaseMake
{
    public string $signature = 'make:resource';
    protected string $type = 'Resource';
    protected string $directory = 'app/Http/Resources';
    protected string $stub = <<<'EOT'
<?php

namespace App\Http\Resources;

class {{name}}
{
    public function __construct(protected mixed $resource)
    {
    }

    /**
     * Transform the resource into an array.
     */
    public function toArray(): array
    {
        return (array) $this->resource;
    }
}
EOT;
}
