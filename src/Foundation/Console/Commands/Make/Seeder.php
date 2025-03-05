<?php

namespace Eyika\Atom\Framework\Foundation\Console\Commands\Make;


class Seeder extends BaseMake
{
    public string $signature = 'make:seeder';
    public string $description = 'Create a new database seeder';
    protected string $type = 'Seeder';
    protected string $directory = 'database/seeds';

    protected string $stub = <<<EOT
<?php

namespace Database\Seeds;

use Atom\Framework\Support\Database\DB;
use Eyika\Atom\Framework\Database\Seeder\Seeder;

class {{name}} extends Seeder
{
    protected string \$table = '';

    public function run()
    {
        \$data = [];
        // Insert seeder logic here
        \$this->insert(\$data);
    }
}

EOT;
}
