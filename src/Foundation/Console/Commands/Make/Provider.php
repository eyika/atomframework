<?php

namespace Eyika\Atom\Framework\Foundation\Console\Commands\Make;

class Provider extends BaseMake
{
    public string $signature = 'make:provider';
    protected string $type = 'Provider';
    protected string $directory = 'app/Providers';
    protected string $stub = <<<'EOT'
<?php

namespace App\Providers;

use Eyika\Atom\Framework\Foundation\ServiceProvider;

class {{name}} extends ServiceProvider
{
    /**
     * Register container bindings.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services — register routes/config/migrations/commands/views here.
     */
    public function boot(): void
    {
        //
    }
}
EOT;
}
