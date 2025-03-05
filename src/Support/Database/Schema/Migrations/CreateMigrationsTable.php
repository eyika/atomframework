<?php

namespace Eyika\Atom\Framework\Support\Database\Schema\Migrations;

use Eyika\Atom\Framework\Support\Database\Schema\Blueprint;
use Eyika\Atom\Framework\Support\Database\Schema\Schema;

class CreateMigrationsTable
{
    protected $table = 'migrations';

    public function up()
    {
        Schema::create('migrations', function (Blueprint $table) {
            $table->id();
            $table->string('migration')->varChar(255)->notNullable();
            $table->integer('batch')->notNullable();
            $table->timestamp('start_time');
            $table->timestamps();
        });
        // DB::statement("CREATE TABLE IF NOT EXISTS migrations (
        //     id INT AUTO_INCREMENT PRIMARY KEY,
        //     migration VARCHAR(255) NOT NULL,
        //     batch INT NOT NULL
        // )");
    }

    public function down()
    {
        Schema::dropIfExists($this->table);
        // DB::statement("DROP TABLE IF EXISTS migrations");
    }
}