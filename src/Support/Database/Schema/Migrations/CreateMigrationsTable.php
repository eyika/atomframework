<?php

namespace Eyika\Atom\Framework\Support\Database\Schema\Migrations;

use Eyika\Atom\Framework\Support\Database\Schema\Blueprint;
use Eyika\Atom\Framework\Support\Database\Schema\Schema;

class CreateMigrationsTable
{
    protected $table = 'migrations';

    public function up()
    {
        if (!Schema::hasTable('migrations')) {
            Schema::create('migrations', function (Blueprint $table) {
                $table->id();
                $table->string('migration')->varChar(191)->notNullable()->charset('utf8mb4')->collation('utf8mb4_unicode_ci')->comment('sampple comment');
                $table->integer('batch')->notNullable();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists($this->table);
    }
}
