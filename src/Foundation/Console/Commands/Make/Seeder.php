<?php

namespace Eyika\Atom\Framework\Foundation\Console\Commands\Make;

use Eyika\Atom\Framework\Exceptions\Console\BaseConsoleException;
use Eyika\Atom\Framework\Support\Str;

class Seeder extends BaseMake
{
    public string $signature = 'make:seeder {--table=}';
    public string $description = 'Create a new database seeder';
    protected string $type = 'Seeder';
    protected string $directory = 'database/seeds';

    protected string $stub = <<<EOT
<?php

namespace Database\Seeds;

use Eyika\Atom\Framework\Support\Database\Seeder\Seeder;

class {{name}} extends Seeder
{
    protected string \$table = '{{table}}';

    public function run()
    {
        \$data = [];
        // Insert seeder logic here
        \$this->insert(\$this->table, \$data);
    }
}

EOT;

    protected function stubContent(): string
    {
        info('', $this->options());
        $table = $this->option('table') ?? Str::plural(str_replace('_seeder', '', Str::snake($this->argument(0))) ?? 'table');
        $content = str_replace('{{table}}', $table, $this->stub);
        return $content;
    }

    protected function filename(): string
    {
        $name =  $this->argument(0);
        if (is_null($name)) {
            throw new BaseConsoleException("Please provide a name for the {$this->type}");
        }
        if (!Str::isPascal($name)) {
            throw new BaseConsoleException("Seeder name should be in PascalCase");
        }

        return $name;
    }
}
