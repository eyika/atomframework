<?php
namespace Eyika\Atom\Framework\Foundation\Console\Commands\Make;

use Eyika\Atom\Framework\Exceptions\Console\BaseConsoleException;
use Eyika\Atom\Framework\Support\Str;

class Migration extends BaseMake
{
    public string $signature = 'make:migration';
    public string $description = 'Create a new migration file';
    protected string $type = 'Migration';
    protected string $directory = 'database/migrations';

    protected function stubContent(): string
    {
        $table_name = Str::snake($this->argument(0));

        return <<<EOT
<?php

namespace Database\Migrations;

use Eyika\Atom\Framework\Support\Database\Schema\Migrations\Migration;
use Eyika\Atom\Framework\Support\Database\Schema\Blueprint;
use Eyika\Atom\Framework\Support\Database\Schema\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('$table_name', function (Blueprint \$table) {
            \$table->id();
            \$table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('$table_name');
    }
};

EOT;
    }

    protected function filename(): string
    {
        $timestamp = date('Y_m_d_His');
        $name =  $this->argument(0);
        if (is_null($name)) {
            throw new BaseConsoleException("Please provide a name for the {$this->type}");
        }

        return "{$timestamp}_" . Str::snake($name);
    }
}
