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
                $table->string('migration', 191)->notNullable();
                $table->integer('batch')->notNullable();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists($this->table);
    }
}
