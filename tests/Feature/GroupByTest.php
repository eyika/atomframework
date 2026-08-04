<?php

namespace Eyika\Atom\Framework\Tests\Feature;

use Eyika\Atom\Framework\Support\Database\Model;

class GroupedOrder extends Model
{
    public $table = 'atomtest_grouped';
    public $softdeletes = false;

    protected const fillable = ['id', 'customer_id', 'total'];
}

/**
 * Requested by Claude C (vendra): with no GROUP BY, per-key aggregates — lifetime spend, order
 * counts, last-order dates per customer — had to be computed in PHP over the whole table, which
 * is fine at a shop's volume and will not be at a warehouse's.
 *
 * groupBy()/having() plus a chainable select()/selectRaw() now express that in SQL. selectRaw is
 * deliberately separate from select(): it is the one place the builder emits caller-supplied SQL
 * verbatim, and the name is what marks it as never-for-user-input.
 */
class GroupByTest extends DatabaseTestCase
{
    protected function createSchema(): void
    {
        $this->raw('DROP TABLE IF EXISTS atomtest_grouped');
        $this->raw('CREATE TABLE atomtest_grouped (
            id INT AUTO_INCREMENT PRIMARY KEY,
            customer_id INT NOT NULL,
            total INT NOT NULL,
            created_at DATETIME NULL,
            updated_at DATETIME NULL
        )');
        $this->raw('INSERT INTO atomtest_grouped (customer_id, total) VALUES
            (1, 100), (1, 250), (2, 70), (3, 400), (3, 10), (3, 90)');
    }

    protected function dropSchema(): void
    {
        $this->raw('DROP TABLE IF EXISTS atomtest_grouped');
    }

    /** @return array<int|string, int> customer_id => aggregate */
    private function pairs(mixed $rows, string $key, string $value): array
    {
        $rows = is_array($rows) ? $rows : $rows->all();
        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->{$key}] = (int) $row->{$value};
        }
        ksort($out);
        return $out;
    }

    /** The reported use case: lifetime spend per customer, computed by the database. */
    public function test_group_by_with_an_aggregate_projection(): void
    {
        $rows = (new GroupedOrder())
            ->select(['customer_id'])
            ->selectRaw('SUM(total) AS lifetime')
            ->groupBy('customer_id')
            ->get();

        $this->assertSame([1 => 350, 2 => 70, 3 => 500], $this->pairs($rows, 'customer_id', 'lifetime'));
    }

    public function test_group_by_with_a_count_per_key(): void
    {
        $rows = (new GroupedOrder())
            ->select(['customer_id'])
            ->selectRaw('COUNT(*) AS orders')
            ->groupBy('customer_id')
            ->get();

        $this->assertSame([1 => 2, 2 => 1, 3 => 3], $this->pairs($rows, 'customer_id', 'orders'));
    }

    public function test_having_filters_on_the_aggregate(): void
    {
        $rows = (new GroupedOrder())
            ->select(['customer_id'])
            ->selectRaw('SUM(total) AS lifetime')
            ->groupBy('customer_id')
            ->having('SUM(total)', '>', 100)
            ->get();

        $this->assertSame([1 => 350, 3 => 500], $this->pairs($rows, 'customer_id', 'lifetime'));
    }

    public function test_where_narrows_the_set_before_grouping(): void
    {
        $rows = (new GroupedOrder())
            ->where('total', '>', 50)
            ->select(['customer_id'])
            ->selectRaw('SUM(total) AS lifetime')
            ->groupBy('customer_id')
            ->get();

        // customer 3 loses its 10; customer 2 keeps its 70.
        $this->assertSame([1 => 350, 2 => 70, 3 => 490], $this->pairs($rows, 'customer_id', 'lifetime'));
    }

    public function test_group_by_combines_with_order_by_and_limit(): void
    {
        $rows = (new GroupedOrder())
            ->select(['customer_id'])
            ->selectRaw('SUM(total) AS lifetime')
            ->groupBy('customer_id')
            ->orderBy('lifetime', 'DESC')
            ->limit(2)
            ->get();

        $this->assertSame([1 => 350, 3 => 500], $this->pairs($rows, 'customer_id', 'lifetime'));
    }

    /**
     * Clause order must not depend on CALL order — limit() before orderBy() previously emitted
     * `LIMIT 2 ORDER BY …`, a syntax error. Clauses are now emitted in fixed SQL order.
     */
    public function test_clause_order_is_independent_of_call_order(): void
    {
        $byCallOrder = (new GroupedOrder())->limit(2)->orderBy('total', 'ASC')->get();
        $rows = is_array($byCallOrder) ? $byCallOrder : $byCallOrder->all();

        $this->assertCount(2, $rows);
        $this->assertSame([10, 70], array_map(fn ($r) => (int) $r->total, $rows));
    }

    public function test_a_chainable_select_projects_columns(): void
    {
        $rows = (new GroupedOrder())->select(['total'])->where('customer_id', 2)->get();
        $rows = is_array($rows) ? $rows : $rows->all();

        $this->assertSame(70, (int) $rows[0]->total);
        $this->assertNull($rows[0]->customer_id, 'an unselected column must not be hydrated');
    }

    public function test_having_rejects_an_expression_that_is_not_an_aggregate_or_column(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new GroupedOrder())->groupBy('customer_id')->having('1=1 UNION SELECT 1', '>', 0)->get();
    }

    public function test_having_rejects_an_unknown_aggregate(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new GroupedOrder())->groupBy('customer_id')->having('EVIL(total)', '>', 0)->get();
    }

    /** The static DB builder gets the same clauses (its projection goes through get()). */
    public function test_the_static_db_builder_groups_and_filters_too(): void
    {
        $rows = \Eyika\Atom\Framework\Support\Database\DB::table('atomtest_grouped')
            ->groupBy('customer_id')
            ->having('SUM(total)', '>', 100)
            ->get(['customer_id', 'SUM(total) AS lifetime']);

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row['customer_id']] = (int) $row['lifetime'];
        }
        ksort($out);

        $this->assertSame([1 => 350, 3 => 500], $out);
    }

    public function test_group_by_is_callable_statically(): void
    {
        $rows = GroupedOrder::select(['customer_id'])
            ->selectRaw('SUM(total) AS lifetime')
            ->groupBy('customer_id')
            ->get();

        $this->assertSame([1 => 350, 2 => 70, 3 => 500], $this->pairs($rows, 'customer_id', 'lifetime'));
    }
}
