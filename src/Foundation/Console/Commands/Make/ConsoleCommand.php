<?php

namespace Eyika\Atom\Framework\Foundation\Console\Commands\Make;

class ConsoleCommand extends BaseMake
{
    public string $signature = 'make:command';
    protected string $type = 'Command';
    protected string $directory = 'app/Console/Commands';
    protected string $stub = <<<'EOT'
<?php

namespace App\Console\Commands;

use Eyika\Atom\Framework\Foundation\Console\Command;

class {{name}} extends Command
{
    public string $signature = 'app:command-name';
    public string $description = 'Command description';

    public function handle(): bool
    {
        $this->info('{{name}} executed.');

        return true;
    }
}
EOT;
}
