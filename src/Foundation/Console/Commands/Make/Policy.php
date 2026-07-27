<?php

namespace Eyika\Atom\Framework\Foundation\Console\Commands\Make;

class Policy extends BaseMake
{
    public string $signature = 'make:policy';
    protected string $type = 'Policy';
    protected string $directory = 'app/Policies';
    protected string $stub = <<<'EOT'
<?php

namespace App\Policies;

class {{name}}
{
    /**
     * Example ability check.
     */
    public function view($user, $model): bool
    {
        return false;
    }
}
EOT;
}
