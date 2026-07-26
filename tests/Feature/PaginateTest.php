<?php

namespace Eyika\Atom\Framework\Tests\Feature;

use Eyika\Atom\Framework\Support\Database\Model;
use Eyika\Atom\Framework\Support\Database\PaginatedData;
use ReflectionProperty;

class PaginateDoc extends Model
{
    public $table = 'atomtest_docs';
    protected $fillable = ['id', 'name'];
    // softdeletes defaults to true — the point of this test.
}

/**
 * Covers BUG-27: _paginate() calls __aggregate(reset=false) then _all() on the SAME
 * instance, and both pushed "deleted_at IS NULL" + an "AND" onto $this->or_ands, so
 * the second query got a dangling/duplicated boolean. Now each builds the predicate
 * on a local copy, so paginate returns the correct active rows.
 */
class PaginateTest extends DatabaseTestCase
{
    protected function createSchema(): void
    {
        $this->raw('DROP TABLE IF EXISTS atomtest_docs');
        $this->raw('CREATE TABLE atomtest_docs (id INT PRIMARY KEY, name VARCHAR(50), created_at DATETIME NULL, updated_at DATETIME NULL, deleted_at DATETIME NULL)');
        $this->raw("INSERT INTO atomtest_docs (id, name, deleted_at) VALUES (1,'a',NULL),(2,'b',NULL),(3,'c',NULL),(4,'d','2020-01-01 00:00:00')");
    }

    protected function dropSchema(): void
    {
        $this->raw('DROP TABLE IF EXISTS atomtest_docs');
    }

    public function test_paginate_with_softdeletes_returns_only_active_rows(): void
    {
        $result = (new PaginateDoc())->paginate(1, 10);

        // Before the fix, the double-appended AND made the _all() query malformed.
        $this->assertInstanceOf(PaginatedData::class, $result);

        $total = new ReflectionProperty(PaginatedData::class, 'total_records');
        $total->setAccessible(true);
        $data = new ReflectionProperty(PaginatedData::class, 'data');
        $data->setAccessible(true);

        $this->assertSame(3, $total->getValue());      // soft-deleted row excluded
        $this->assertCount(3, $data->getValue());       // the page has the 3 active rows
    }
}
