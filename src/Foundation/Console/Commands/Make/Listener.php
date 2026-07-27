<?php

namespace Eyika\Atom\Framework\Foundation\Console\Commands\Make;

class Listener extends BaseMake
{
    public string $signature = 'make:listener';
    protected string $type = 'Listener';
    protected string $directory = 'app/Listeners';
    protected string $stub = <<<'EOT'
<?php

namespace App\Listeners;

class {{name}}
{
    /**
     * Handle the event.
     */
    public function handle($event): void
    {
        //
    }
}
EOT;
}
