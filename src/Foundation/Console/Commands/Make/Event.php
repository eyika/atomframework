<?php

namespace Eyika\Atom\Framework\Foundation\Console\Commands\Make;

class Event extends BaseMake
{
    public string $signature = 'make:event';
    protected string $type = 'Event';
    protected string $directory = 'app/Events';
    protected string $stub = <<<'EOT'
<?php

namespace App\Events;

class {{name}}
{
    public function __construct()
    {
        //
    }
}
EOT;
}
