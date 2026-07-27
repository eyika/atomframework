<?php

namespace Eyika\Atom\Framework\Foundation\Console\Commands\Make;

class Job extends BaseMake
{
    public string $signature = 'make:job';
    protected string $type = 'Job';
    protected string $directory = 'app/Jobs';
    protected string $stub = <<<'EOT'
<?php

namespace App\Jobs;

use Eyika\Atom\Framework\Foundation\Console\Concerns\ShouldQueue;

class {{name}}
{
    use ShouldQueue;

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        //
    }
}
EOT;
}
