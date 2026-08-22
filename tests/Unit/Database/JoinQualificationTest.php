<?php

namespace Eyika\Atom\Framework\Tests\Unit\Database;

use Eyika\Atom\Framework\Support\Database\Connection;
use Eyika\Atom\Framework\Support\Database\Model;
use Eyika\Atom\Framework\Support\Facade\DatabaseConnection;
use PHPUnit\Framework\TestCase;

class JoinedOrderItem extends Model
{
    public $table = 'order_items';
    public $softdeletes = false;

    protected const fillable = ['id', 'order_id', 'sku'];
    protected const guarded  = [];
}

/**
 * Reported by Claude C (vendra): `OrderItem::…->join('orders', …)->get()` failed with
 * `ambiguous column name: id`, so a report could not filter child rows by a parent's state in one
 * query.
 *
 * The cause was not the WHERE. A model selects its own fillable columns UNQUALIFIED, so joining
 * any table that shares a column name — `id`, universally — broke the SELECT before a WHERE was
 * involved at all. Bare columns are now qualified with the base table, but ONLY when a join is
 * present: without one there is nothing to disambiguate and prefixing everything would just be
 * noise in the emitted SQL.
 */
class JoinQualificationTest extends TestCase
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

        DatabaseConnection::exec('CREATE TABLE orders (id INTEGER PRIMARY KEY, store_id INT, status TEXT)');
        DatabaseConnection::exec('CREATE TABLE order_items (id INTEGER PRIMARY KEY, order_id INT, sku TEXT)');
        DatabaseConnection::exec("INSERT INTO orders (id, store_id, status) VALUES (1, 7, 'paid'), (2, 7, 'draft')");
        DatabaseConnection::exec("INSERT INTO order_items (id, order_id, sku) VALUES (10, 1, 'a'), (11, 2, 'b')");
    }

    private function ids($rows): array
    {
        $out = [];
        foreach (($rows ?: []) as $row) {
            $out[] = (int) (is_array($row) ? $row['id'] : $row->id);
        }
        sort($out);

        return $out;
    }

    private function items(): JoinedOrderItem
    {
        return new JoinedOrderItem();
    }

    // ---------------------------------------------------------------- the reported failure

    /** This threw `ambiguous column name: id` with no WHERE clause at all. */
    public function test_a_join_alone_no_longer_raises_an_ambiguous_column(): void
    {
        $rows = $this->items()->join('orders', 'order_items.order_id', '=', 'orders.id')->get();

        $this->assertSame([10, 11], $this->ids($rows));
    }

    /** A bare WHERE column across a join has to say which table it means. */
    public function test_a_bare_where_column_resolves_to_the_base_table(): void
    {
        $rows = $this->items()
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->where('id', 10)
            ->get();

        $this->assertSame([10], $this->ids($rows), 'id must mean order_items.id, not orders.id');
    }

    /** The point of the join: filter child rows by a parent's state in one query. */
    public function test_child_rows_can_be_filtered_by_a_parent_column(): void
    {
        $rows = $this->items()
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->where('orders.status', 'paid')
            ->get();

        $this->assertSame([10], $this->ids($rows));
    }

    /** An explicitly qualified column must be left exactly as written. */
    public function test_an_explicitly_qualified_column_is_not_re_qualified(): void
    {
        $rows = $this->items()
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->where('order_items.sku', 'b')
            ->get();

        $this->assertSame([11], $this->ids($rows));
    }

    public function test_a_join_composes_with_trailing_clauses(): void
    {
        $rows = $this->items()
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->where('orders.store_id', 7)
            ->orderBy('id', 'DESC')
            ->limit(1)
            ->get();

        $this->assertSame([11], $this->ids($rows));
    }

    public function test_a_join_composes_with_a_raw_fragment(): void
    {
        $rows = $this->items()
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->whereRaw('orders.status = :s', ['s' => 'draft'])
            ->get();

        $this->assertSame([11], $this->ids($rows));
    }

    // ---------------------------------------------------------------- qualification is join-only

    /**
     * Without a join there is nothing to disambiguate, so the emitted SQL must stay unqualified —
     * this is what keeps the change from rewriting every query in the application.
     */
    public function test_a_query_without_a_join_is_left_unqualified(): void
    {
        $sql = Connection::compileSelectList(['id', 'sku']);

        $this->assertSame('"id", "sku"', $sql);
    }

    public function test_a_query_with_a_join_qualifies_bare_columns(): void
    {
        $sql = Connection::compileSelectList(['id', 'sku'], 'order_items');

        $this->assertSame('"order_items"."id", "order_items"."sku"', $sql);
    }

    public function test_qualification_leaves_stars_expressions_and_qualified_columns_alone(): void
    {
        $sql = Connection::compileSelectList(['*', 'COUNT(id) AS n', 'orders.status'], 'order_items');

        $this->assertStringContainsString('*', $sql);
        $this->assertStringContainsString('COUNT(id) AS n', $sql);
        $this->assertStringContainsString('"orders"."status"', $sql);
        $this->assertStringNotContainsString('"order_items"."orders"', $sql);
    }

    // ---------------------------------------------------------------- no collateral damage

    public function test_ordinary_unjoined_queries_still_work(): void
    {
        $this->assertSame([10, 11], $this->ids($this->items()->get()));
        $this->assertSame([11], $this->ids($this->items()->where('sku', 'b')->get()));
        $this->assertSame(2, $this->items()->count());
    }
}
