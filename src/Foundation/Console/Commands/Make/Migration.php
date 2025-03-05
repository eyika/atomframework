<?php
namespace Atom\Framework\Console\Commands;

use Atom\Framework\Console\BaseMake;

class Migration extends BaseMake
{
    protected string $name = 'make:migration';
    protected string $description = 'Create a new migration file';
    protected string $type = 'Migration';
    protected string $directory = 'migrations';

    protected function stubContent(): string
    {
        $timestamp = date('Y_m_d_His');
        return <<<EOT
<?php

namespace Database\Migrations;

use Atom\Framework\Support\Database\Schema;
use Atom\Framework\Support\Database\Blueprint;

class {$timestamp}_{{name}}
{
    public function up()
    {
        Schema::create('table_name', function (Blueprint \$table) {
            \$table->id();
            \$table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('table_name');
    }
}

EOT;
    }
}
