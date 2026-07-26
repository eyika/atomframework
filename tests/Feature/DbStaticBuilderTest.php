<?php

namespace Eyika\Atom\Framework\Tests\Feature;

use Eyika\Atom\Framework\Support\Database\DB;
use Eyika\Atom\Framework\Support\Database\PaginatedData;
use ReflectionProperty;

/**
 * Covers BUG-31: the static DB builder kept ALL query-state (table, where filter,
 * operators, or/and, order, joins, limit) in STATIC properties, so two live builders
 * clobbered each other, and several methods were broken (paginate used an undefined
 * `static::$offset` variable-variable) or unimplemented (findOr/firstWhere/
 * firstOrCreate/firstOrNew threw NotImplementedException). Query-state is now INSTANCE
 * state; paginate is fixed; the four stubs are implemented.
 */
class DbStaticBuilderTest extends DatabaseTestCase
{
    protected function createSchema(): void
    {
        $this->raw('DROP TABLE IF EXISTS atomtest_kv');
        $this->raw('CREATE TABLE atomtest_kv (id INT AUTO_INCREMENT PRIMARY KEY, k VARCHAR(20), v VARCHAR(50))');
        $this->raw("INSERT INTO atomtest_kv (id,k,v) VALUES (1,'a','one'),(2,'b','two'),(3,'c','three'),(4,'d','four'),(5,'e','five')");
    }

    protected function dropSchema(): void
    {
        $this->raw('DROP TABLE IF EXISTS atomtest_kv');
    }

    public function test_two_interleaved_builders_do_not_clobber_each_other(): void
    {
        // Build A (filter k=a), then build AND execute B (filter k=b). If query-state
        // were static, constructing/running B would have overwritten A's WHERE — the
        // core BUG-31 regression.
        $a = DB::table('atomtest_kv')->where('k', 'a');
        $b = DB::table('atomtest_kv')->where('k', 'b');

        $rowB = $b->first();
        $rowA = $a->first();

        $this->assertIsArray($rowA);
        $this->assertIsArray($rowB);
        $this->assertSame('a', $rowA['k']);
        $this->assertSame('b', $rowB['k']);
    }

    public function test_where_filter_is_isolated_per_builder_instance(): void
    {
        $count = DB::table('atomtest_kv')->where('k', 'c')->count();
        $this->assertSame(1, (int) $count);

        // A fresh builder must start clean, not inherit the previous WHERE.
        $all = DB::table('atomtest_kv')->all();
        $this->assertCount(5, $all);
    }

    public function test_paginate_returns_correct_page_and_total(): void
    {
        $result = DB::table('atomtest_kv')->paginate(1, 2);
        $this->assertInstanceOf(PaginatedData::class, $result);

        $total = new ReflectionProperty(PaginatedData::class, 'total_records');
        $total->setAccessible(true);
        $data = new ReflectionProperty(PaginatedData::class, 'data');
        $data->setAccessible(true);

        $this->assertSame(5, $total->getValue());
        $this->assertCount(2, $data->getValue());

        // Second page — LIMIT/OFFSET applied correctly.
        DB::table('atomtest_kv')->paginate(2, 2);
        $this->assertCount(2, $data->getValue());

        // Last (partial) page.
        DB::table('atomtest_kv')->paginate(3, 2);
        $this->assertCount(1, $data->getValue());
    }

    public function test_first_where(): void
    {
        $row = DB::table('atomtest_kv')->firstWhere('k', 'c');
        $this->assertIsArray($row);
        $this->assertSame('three', $row['v']);

        $this->assertFalse(DB::table('atomtest_kv')->firstWhere('k', 'nope'));
    }

    public function test_find_or_runs_callable_only_on_miss(): void
    {
        $hit = DB::table('atomtest_kv')->findOr(1, fn () => 'CALLED');
        $this->assertIsArray($hit);
        $this->assertSame('a', $hit['k']);

        $miss = DB::table('atomtest_kv')->findOr(9999, fn () => 'CALLED');
        $this->assertSame('CALLED', $miss);
    }

    public function test_first_or_create_returns_existing_without_inserting(): void
    {
        $row = DB::table('atomtest_kv')->firstOrCreate(['k' => 'a'], ['k' => 'a', 'v' => 'SHOULD_NOT_WRITE']);
        $this->assertSame('one', $row['v']);          // original value, not overwritten
        $this->assertSame(5, (int) DB::table('atomtest_kv')->count());
    }

    public function test_first_or_create_inserts_on_miss(): void
    {
        $row = DB::table('atomtest_kv')->firstOrCreate(['k' => 'z'], ['v' => 'created']);
        $this->assertSame('z', $row['k']);
        $this->assertSame('created', $row['v']);
        $this->assertSame(6, (int) DB::table('atomtest_kv')->count());
    }

    public function test_first_or_new_does_not_persist_on_miss(): void
    {
        $row = DB::table('atomtest_kv')->firstOrNew(['k' => 'zz'], ['v' => 'draft']);
        $this->assertSame(['k' => 'zz', 'v' => 'draft'], $row);

        // Nothing was written.
        $this->assertSame(5, (int) DB::table('atomtest_kv')->count());
        $this->assertFalse(DB::table('atomtest_kv')->where('k', 'zz')->first());
    }
}
