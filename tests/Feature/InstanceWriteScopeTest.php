<?php

namespace Eyika\Atom\Framework\Tests\Feature;

use Eyika\Atom\Framework\Support\Database\DB;
use Eyika\Atom\Framework\Support\Database\Model;

class LocationRow extends Model
{
    public $table = 'atomtest_locations';
    public $softdeletes = false;
    const fillable = ['id', 'store_id', 'is_default'];
}

/**
 * Regression: a model hydrated from a MULTI-ROW read (`->where('store_id', X)->get()`) clones the
 * builder, inheriting that query's WHERE. Instance writes (`$row->update()` / `$row->delete()`)
 * previously reused that filter instead of the row's primary key — so one write hit EVERY row in
 * the store. An instance write must target exactly that row; a bulk `where(...)->update/delete`
 * must still affect all matching rows.
 */
class InstanceWriteScopeTest extends DatabaseTestCase
{
    protected function createSchema(): void
    {
        $this->raw('DROP TABLE IF EXISTS atomtest_locations');
        $this->raw('CREATE TABLE atomtest_locations (id INT AUTO_INCREMENT PRIMARY KEY, store_id INT, is_default INT DEFAULT 0)');
        // rows 1 & 2 → store 1; row 3 → store 2
        $this->raw("INSERT INTO atomtest_locations (id, store_id, is_default) VALUES (1,1,0),(2,1,0),(3,2,0)");
    }

    protected function dropSchema(): void
    {
        $this->raw('DROP TABLE IF EXISTS atomtest_locations');
    }

    private function rowFromMultiRead(int $id): LocationRow
    {
        $rows = (new LocationRow())->where('store_id', 1)->get();   // clones carry store_id = 1
        foreach ($rows as $r) {
            if ((int) $r->id === $id) {
                return $r;
            }
        }
        $this->fail("row $id not found in multi-row read");
    }

    private function isDefault(int $id): int
    {
        return (int) DB::table('atomtest_locations')->where('id', $id)->first()['is_default'];
    }

    public function test_instance_update_targets_only_that_row(): void
    {
        $this->rowFromMultiRead(1)->update(['is_default' => 1]);

        $this->assertSame(1, $this->isDefault(1)); // this row updated
        $this->assertSame(0, $this->isDefault(2)); // sibling in the SAME store must be untouched (was 1 before the fix)
    }

    public function test_instance_delete_targets_only_that_row(): void
    {
        $this->rowFromMultiRead(1)->delete();

        $this->assertFalse(DB::table('atomtest_locations')->where('id', 1)->first()); // deleted
        $this->assertNotFalse(DB::table('atomtest_locations')->where('id', 2)->first()); // sibling survives (was deleted before the fix)
    }

    public function test_bulk_update_still_affects_all_matching_rows(): void
    {
        // A scratch where(...)->update() is NOT a saved instance, so it keeps its filter (bulk).
        (new LocationRow())->where('store_id', 1)->update(['is_default' => 7]);

        $this->assertSame(7, $this->isDefault(1));
        $this->assertSame(7, $this->isDefault(2));
        $this->assertSame(0, $this->isDefault(3)); // other store untouched
    }
}
