<?php

namespace Eyika\Atom\Framework\Tests\Feature;

use Eyika\Atom\Framework\Support\Database\DB;

/**
 * Validates the DB-backed harness end-to-end against MySQL, and exercises the
 * delete-safety guard on the happy path (a filtered delete must still work).
 */
class DatabaseSmokeTest extends DatabaseTestCase
{
    protected function createSchema(): void
    {
        $this->raw('DROP TABLE IF EXISTS atomtest_items');
        $this->raw('CREATE TABLE atomtest_items (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(50), qty INT)');
        $this->raw("INSERT INTO atomtest_items (name, qty) VALUES ('apple', 3), ('banana', 5)");
    }

    protected function dropSchema(): void
    {
        $this->raw('DROP TABLE IF EXISTS atomtest_items');
    }

    public function test_where_first_reads_a_row(): void
    {
        $row = DB::table('atomtest_items')->where('name', 'apple')->first();

        $this->assertIsArray($row);
        $this->assertSame('apple', $row['name']);
        $this->assertEquals(3, $row['qty']);
    }

    public function test_filtered_delete_removes_only_matching(): void
    {
        DB::table('atomtest_items')->where('name', 'apple')->delete();

        $this->assertFalse(DB::table('atomtest_items')->where('name', 'apple')->first());
        $this->assertIsArray(DB::table('atomtest_items')->where('name', 'banana')->first());
    }
}
