<?php

namespace Eyika\Atom\Framework\Tests\Feature;

use Eyika\Atom\Framework\Support\Collections\Collection;
use Eyika\Atom\Framework\Support\Collections\LazyCollection;
use Eyika\Atom\Framework\Support\Database\Model;
use Eyika\Atom\Framework\Support\Database\PaginatedData;

class CursorRow extends Model
{
    public $table = 'atomtest_cursor';
    public $softdeletes = false;
    const fillable = ['id', 'name'];
}

/**
 * Covers the three-tier collection support:
 *  - get()/all() → Collection (eager)
 *  - cursor()/lazy() → LazyCollection (streamed one model at a time from fetch_cursor)
 */
class CursorTest extends DatabaseTestCase
{
    protected function createSchema(): void
    {
        $this->raw('DROP TABLE IF EXISTS atomtest_cursor');
        $this->raw('CREATE TABLE atomtest_cursor (id INT PRIMARY KEY, name VARCHAR(20))');
        $this->raw("INSERT INTO atomtest_cursor (id, name) VALUES (1,'a'),(2,'b'),(3,'c'),(4,'d'),(5,'e')");
    }

    protected function dropSchema(): void
    {
        $this->raw('DROP TABLE IF EXISTS atomtest_cursor');
    }

    public function test_all_returns_a_collection(): void
    {
        $rows = (new CursorRow())->all();

        $this->assertInstanceOf(Collection::class, $rows);
        $this->assertCount(5, $rows);
    }

    public function test_cursor_returns_a_lazy_collection_of_models(): void
    {
        $cursor = (new CursorRow())->cursor();

        $this->assertInstanceOf(LazyCollection::class, $cursor);

        $names = [];
        foreach ($cursor as $row) {
            $this->assertInstanceOf(CursorRow::class, $row);
            $names[] = $row->name;
        }

        $this->assertSame(['a', 'b', 'c', 'd', 'e'], $names);
    }

    public function test_cursor_honours_where(): void
    {
        $cursor = (new CursorRow())->where('name', 'c')->lazy();

        $collected = [];
        foreach ($cursor as $row) {
            $collected[] = $row->name;
        }

        $this->assertSame(['c'], $collected);
    }

    public function test_cursor_lazy_api_maps_without_buffering(): void
    {
        // LazyCollection supports the fluent API lazily.
        $upper = (new CursorRow())->cursor()
            ->map(fn ($row) => strtoupper($row->name))
            ->take(2)
            ->all();

        $this->assertSame(['A', 'B'], $upper);
    }

    public function test_paginate_items_are_available_as_a_collection(): void
    {
        $page = (new CursorRow())->paginate(1, 10);

        $this->assertInstanceOf(PaginatedData::class, $page);

        $items = PaginatedData::collection();
        $this->assertInstanceOf(Collection::class, $items);
        $this->assertCount(5, $items);
    }
}
