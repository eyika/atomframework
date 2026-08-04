<?php

namespace Eyika\Atom\Framework\Tests\Feature;

use Eyika\Atom\Framework\Support\Database\Model;
use Eyika\Atom\Framework\Support\Facade\DatabaseConnection;

class AggOrder extends Model
{
    public $table = 'atomtest_agg_orders';
    public $softdeletes = false;

    protected const fillable = ['id', 'customer_id', 'total'];
}

/**
 * Found while checking Claude C's report that aggregates were unavailable on the model builder.
 * They are available — but every one except count() was running on the WRONG CONNECTION.
 *
 * Connection::__callStatic did `new static(config('database'))` unconditionally, and the
 * aggregates (sum/avg/min/max/stddev/…) dispatch through it as `sum_total`, `avg_total` and so
 * on. So each call opened a separate connection: it ignored a swapped test connection, and —
 * the sharp edge — inside a transaction it could not see the transaction's own uncommitted
 * writes, silently returning a stale figure. It now uses the bound connection.
 */
class AggregateConnectionTest extends DatabaseTestCase
{
    protected function createSchema(): void
    {
        $this->raw('DROP TABLE IF EXISTS atomtest_agg_orders');
        $this->raw('CREATE TABLE atomtest_agg_orders (
            id INT AUTO_INCREMENT PRIMARY KEY,
            customer_id INT NOT NULL,
            total INT NOT NULL,
            created_at DATETIME NULL,
            updated_at DATETIME NULL
        ) ENGINE=InnoDB');
        $this->raw('INSERT INTO atomtest_agg_orders (customer_id, total) VALUES
            (1, 100), (1, 250), (2, 70), (3, 400), (3, 10)');
    }

    protected function dropSchema(): void
    {
        $this->raw('DROP TABLE IF EXISTS atomtest_agg_orders');
    }

    public function test_aggregates_are_available_on_the_model_builder(): void
    {
        $this->assertEquals(5, (new AggOrder())->count());
        $this->assertEquals(830, (new AggOrder())->sum('total'));
        $this->assertEquals(400, (new AggOrder())->max('total'));
        $this->assertEquals(10, (new AggOrder())->min('total'));
        $this->assertEquals(166, (int) (new AggOrder())->avg('total'));
    }

    public function test_an_aggregate_respects_a_where_filter(): void
    {
        $this->assertEquals(350, (new AggOrder())->where('customer_id', 1)->sum('total'));
        $this->assertEquals(410, (new AggOrder())->where('customer_id', 3)->sum('total'));
    }

    /**
     * The regression that matters: an aggregate must run on the same connection as everything
     * else, so it sees writes made earlier in the same transaction. Previously this returned the
     * committed-only total, because the aggregate had opened its own connection.
     */
    public function test_an_aggregate_sees_uncommitted_writes_in_the_current_transaction(): void
    {
        $connection = DatabaseConnection::getFacadeRoot();
        $this->assertNotNull($connection, 'the test harness must have a bound connection');

        $connection->beginTransaction();

        try {
            $this->raw('INSERT INTO atomtest_agg_orders (customer_id, total) VALUES (9, 1000)');

            $this->assertEquals(
                1830,
                (new AggOrder())->sum('total'),
                'the aggregate ran on a different connection and could not see the pending row'
            );
        } finally {
            $connection->rollback();
        }

        // And the rollback is visible too — proof the aggregate is not reading a stale snapshot.
        $this->assertEquals(830, (new AggOrder())->sum('total'));
    }

    /** count() always used the bound connection; assert it stays that way. */
    public function test_count_also_sees_uncommitted_writes(): void
    {
        $connection = DatabaseConnection::getFacadeRoot();
        $connection->beginTransaction();

        try {
            $this->raw('INSERT INTO atomtest_agg_orders (customer_id, total) VALUES (9, 5)');
            $this->assertEquals(6, (new AggOrder())->count());
        } finally {
            $connection->rollback();
        }
    }

    /**
     * Column projection also exists, contrary to the report — the read methods take a $select
     * list, so a caller need not hydrate every column.
     */
    public function test_reads_can_project_a_column_subset(): void
    {
        $rows = (new AggOrder())->where('customer_id', 3)->get(true, ['total']);
        $rows = is_array($rows) ? $rows : $rows->all();

        $totals = array_map(fn ($r) => (int) $r->total, $rows);
        sort($totals);

        $this->assertSame([10, 400], $totals);
        $this->assertNull($rows[0]->customer_id, 'an unselected column must not be hydrated');
    }
}
