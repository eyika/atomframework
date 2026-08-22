<?php

namespace Eyika\Atom\Framework\Tests\Unit\Database;

use Eyika\Atom\Framework\Support\Database\Connection;
use Eyika\Atom\Framework\Support\Database\DB;
use Eyika\Atom\Framework\Support\Database\Model;
use Eyika\Atom\Framework\Support\Facade\DatabaseConnection;
use PHPUnit\Framework\TestCase;

class RawStock extends Model
{
    public $table = 'stock';
    public $softdeletes = false;

    protected const fillable = ['id', 'sku', 'quantity', 'reorder_level', 'region'];
    protected const guarded  = [];
}

/**
 * Reported by Claude C (vendra): neither builder had `whereRaw`, so a report could not filter on a
 * predicate the builder cannot express — a column compared to another column being the plain case.
 *
 * I deferred this once, because the seam is `fetch_cursor()`'s reserved-clause map and that
 * function's `$len`/`$j`/`$i` bookkeeping is exactly where the operator-index misalignment bug
 * lived. The tests below pin that bookkeeping in both directions: a raw fragment must not consume
 * a connector slot, and it must combine correctly with ordinary conditions on either side of it.
 *
 * The write path is covered deliberately. `update()`/`delete()` go through `filter()`, NOT
 * `fetch_cursor()`, so a raw predicate the write path ignored would silently WIDEN the write to
 * every row the remaining conditions matched.
 */
class WhereRawTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (!extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite is not enabled.');
        }

        $conn = new Connection([
            'default'     => 'sqlite',
            'connections' => ['sqlite' => ['database' => ':memory:']],
        ]);
        $conn->connect();
        DatabaseConnection::swap($conn);

        DatabaseConnection::exec(
            'CREATE TABLE stock (id INTEGER PRIMARY KEY, sku TEXT, quantity INT, reorder_level INT, region TEXT)'
        );

        $rows = [
            [1, 'aaa', 2, 10, 'eu'],   // below reorder
            [2, 'bbb', 50, 10, 'eu'],  // fine
            [3, 'ccc', 1, 5, 'us'],    // below reorder
            [4, 'ddd', 99, 5, 'us'],   // fine
        ];

        foreach ($rows as $row) {
            DatabaseConnection::exec(
                'INSERT INTO stock (id, sku, quantity, reorder_level, region) VALUES (?, ?, ?, ?, ?)',
                $row
            );
        }
    }

    /** @return array<int,int> sorted ids */
    private function ids($rows): array
    {
        $out = [];
        foreach (($rows ?: []) as $row) {
            $out[] = (int) (is_array($row) ? $row['id'] : $row->id);
        }
        sort($out);

        return $out;
    }

    // ---------------------------------------------------------------- the reported gap

    /** A column compared to another column — what the builder cannot express. */
    public function test_model_builder_filters_on_a_column_to_column_predicate(): void
    {
        $this->assertSame([1, 3], $this->ids((new RawStock())->whereRaw('quantity < reorder_level')->get()));
    }

    public function test_db_builder_filters_on_a_column_to_column_predicate(): void
    {
        $this->assertSame([1, 3], $this->ids(DB::table('stock')->whereRaw('quantity < reorder_level')->get()));
    }

    // ---------------------------------------------------------------- combining with real conditions

    public function test_a_raw_fragment_combines_with_a_preceding_where(): void
    {
        $rows = (new RawStock())->where('region', 'us')->whereRaw('quantity < reorder_level')->get();

        $this->assertSame([3], $this->ids($rows));
    }

    /** The order the builder recorded them in must not matter. */
    public function test_a_raw_fragment_combines_with_a_following_where(): void
    {
        $rows = (new RawStock())->whereRaw('quantity < reorder_level')->where('region', 'us')->get();

        $this->assertSame([3], $this->ids($rows));
    }

    public function test_a_raw_fragment_combines_with_conditions_on_both_sides(): void
    {
        $rows = (new RawStock())
            ->where('region', 'eu')
            ->whereRaw('quantity < reorder_level')
            ->where('sku', 'aaa')
            ->get();

        $this->assertSame([1], $this->ids($rows));
    }

    public function test_successive_raw_fragments_accumulate_with_and(): void
    {
        $rows = (new RawStock())
            ->whereRaw('quantity < reorder_level')
            ->whereRaw('region = :r', ['r' => 'us'])
            ->get();

        $this->assertSame([3], $this->ids($rows));
    }

    // ---------------------------------------------------------------- bindings

    public function test_bindings_are_bound_not_interpolated(): void
    {
        $rows = (new RawStock())->whereRaw('quantity > :floor', ['floor' => 49])->get();

        $this->assertSame([2, 4], $this->ids($rows));
    }

    /**
     * A raw bind named after a real column must not collide with the bind `where()` generates for
     * that same column — the names are rewritten to a unique prefix precisely for this.
     */
    public function test_a_raw_bind_cannot_collide_with_a_column_bind(): void
    {
        $rows = (new RawStock())
            ->where('quantity', 50)
            ->whereRaw('reorder_level < :quantity', ['quantity' => 20])
            ->get();

        $this->assertSame([2], $this->ids($rows));
    }

    public function test_a_bind_name_that_prefixes_another_is_not_clobbered(): void
    {
        $rows = (new RawStock())
            ->whereRaw('quantity > :id AND reorder_level < :id_max', ['id' => 1, 'id_max' => 20])
            ->get();

        // rows 1, 2, 4 have quantity > 1 and reorder_level < 20 (row 3's quantity is exactly 1).
        // If :id had clobbered the :id_max prefix, the second placeholder would be left unbound.
        $this->assertSame([1, 2, 4], $this->ids($rows));
    }

    // ---------------------------------------------------------------- OR grouping

    /**
     * The fragment is parenthesised, so its own OR cannot rebind against the surrounding AND:
     * `region = 'eu' AND (a OR b)` is not `region = 'eu' AND a OR b`.
     */
    public function test_an_or_inside_a_fragment_is_parenthesised(): void
    {
        $rows = (new RawStock())
            ->where('region', 'eu')
            ->whereRaw('sku = :a OR sku = :b', ['a' => 'aaa', 'b' => 'ccc'])
            ->get();

        $this->assertSame([1], $this->ids($rows), 'the OR escaped its parentheses and matched us rows');
    }

    // ---------------------------------------------------------------- the write path

    /** `delete()` goes through filter(), not fetch_cursor() — a dropped predicate would widen it. */
    public function test_delete_honours_a_raw_fragment(): void
    {
        (new RawStock())->whereRaw('quantity < reorder_level')->delete();

        $this->assertSame([2, 4], $this->ids(DB::table('stock')->get()));
    }

    public function test_update_honours_a_raw_fragment(): void
    {
        (new RawStock())->whereRaw('quantity < reorder_level')->update(['region' => 'flagged']);

        $this->assertSame([1, 3], $this->ids(DB::table('stock')->where('region', 'flagged')->get()));
    }

    public function test_delete_combines_a_raw_fragment_with_a_column_condition(): void
    {
        (new RawStock())->where('region', 'us')->whereRaw('quantity < reorder_level')->delete();

        $this->assertSame([1, 2, 4], $this->ids(DB::table('stock')->get()));
    }

    // ---------------------------------------------------------------- no collateral damage

    public function test_an_empty_fragment_is_ignored(): void
    {
        $rows = (new RawStock())->whereRaw('   ')->where('region', 'us')->get();

        $this->assertSame([3, 4], $this->ids($rows));
    }

    public function test_ordinary_queries_are_unaffected(): void
    {
        $this->assertSame([1, 2], $this->ids((new RawStock())->where('region', 'eu')->get()));
        $this->assertSame(4, (new RawStock())->count());
    }

    public function test_a_raw_fragment_composes_with_trailing_clauses(): void
    {
        $rows = (new RawStock())
            ->whereRaw('quantity < reorder_level')
            ->orderBy('id', 'DESC')
            ->limit(1)
            ->get();

        $this->assertSame([3], $this->ids($rows));
    }

    public function test_whereraw_is_reachable_statically(): void
    {
        $this->assertSame([1, 3], $this->ids(RawStock::whereRaw('quantity < reorder_level')->get()));
    }
}
