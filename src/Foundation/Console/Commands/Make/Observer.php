<?php

namespace Eyika\Atom\Framework\Foundation\Console\Commands\Make;

class Observer extends BaseMake
{
    public string $signature = 'make:observer';
    protected string $type = 'Observer';
    protected string $directory = 'app/Observers';
    protected string $stub = <<<'EOT'
<?php

namespace App\Observers;

/**
 * Register with a model:  YourModel::observe({{name}}::class);
 * Only the lifecycle methods you keep are wired up. A "before" method
 * (creating/updating/saving/deleting) returning false aborts the write.
 */
class {{name}}
{
    public function creating($model): void
    {
        //
    }

    public function created($model): void
    {
        //
    }

    public function updating($model): void
    {
        //
    }

    public function updated($model): void
    {
        //
    }

    public function deleting($model): void
    {
        //
    }

    public function deleted($model): void
    {
        //
    }
}
EOT;
}
