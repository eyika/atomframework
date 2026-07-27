<?php

namespace Eyika\Atom\Framework\Support\Database\Seeder;

use Eyika\Atom\Framework\Support\Database\DB;

abstract class Seeder
{
    protected string $table = '';

    /**
     * Run the seeder.
     */
    abstract public function run();

    /**
     * Seed into a given table.
     */
    protected function insert(string $table, array $data)
    {
        foreach ($data as $row) {
            DB::table($table)->insert($row);
        }
    }
}
