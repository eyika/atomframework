<?php

namespace Eyika\Atom\Framework\Foundation\Console\Commands\Make;


class Seeder extends BaseMake
{
    protected string $name = 'make:seeder';
    protected string $description = 'Create a new database seeder';
    protected string $type = 'Seeder';
    protected string $directory = 'database/seeds';

    protected string $stub = <<<EOT
<?php

namespace Database\Seeds;

use Atom\Framework\Support\Database\DB;

class {{name}}
{
    public function run()
    {
        // Insert seeder logic here
        DB::table('table_name')->insert([
            // Sample data
        ]);
    }
}

EOT;
}
