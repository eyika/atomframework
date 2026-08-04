<?php

namespace Eyika\Atom\Framework\Tests\Feature;

use Eyika\Atom\Framework\Support\Database\Connection;
use Eyika\Atom\Framework\Support\Database\Model;

class SnakeDonation extends Model
{
    public $table = 'atomtest_snake';
    public $softdeletes = false;

    protected const fillable = ['id', 'campaign_id', 'attempts', 'amount_paid'];
}

/**
 * Reported by Claude C (vendra) while verifying the aggregate API: `sum('campaign_id')` threw
 * "no such column: campaign".
 *
 * __aggregate() dispatches as `{function}_{column}` — `sum_campaign_id` — and Connection's
 * __callStatic split that on EVERY underscore, keeping only the first two parts. So the column was
 * truncated at its first underscore and the helpers were unusable on any snake_case column, which
 * in a typical schema is nearly all of them.
 *
 * A second defect sat in the same expression: the guard tested only the FIRST segment against a
 * list containing multi-word names, so `group_concat_x` read as `group` — not an aggregate — and
 * those never dispatched at all.
 *
 * count() takes no column and so never reaches that code, which is exactly why probing count()
 * made the aggregates look healthy — the same masking that hid the stale-connection bug.
 */
class AggregateColumnNameTest extends DatabaseTestCase
{
    protected function createSchema(): void
    {
        $this->raw('DROP TABLE IF EXISTS atomtest_snake');
        $this->raw('CREATE TABLE atomtest_snake (
            id INT AUTO_INCREMENT PRIMARY KEY,
            campaign_id INT NOT NULL,
            attempts INT NOT NULL,
            amount_paid INT NOT NULL,
            created_at DATETIME NULL,
            updated_at DATETIME NULL
        )');
        $this->raw('INSERT INTO atomtest_snake (campaign_id, attempts, amount_paid) VALUES
            (1, 3, 100), (2, 4, 250)');
    }

    protected function dropSchema(): void
    {
        $this->raw('DROP TABLE IF EXISTS atomtest_snake');
    }

    public function test_aggregates_work_on_a_snake_case_column(): void
    {
        $this->assertEquals(350, (new SnakeDonation())->sum('amount_paid'));
        $this->assertEquals(3, (new SnakeDonation())->sum('campaign_id'));
        $this->assertEquals(2, (new SnakeDonation())->max('campaign_id'));
        $this->assertEquals(1, (new SnakeDonation())->min('campaign_id'));
        $this->assertEquals(175, (int) (new SnakeDonation())->avg('amount_paid'));
    }

    public function test_a_single_word_column_still_works(): void
    {
        $this->assertEquals(7, (new SnakeDonation())->sum('attempts'));
    }

    public function test_a_snake_case_aggregate_respects_a_filter(): void
    {
        $this->assertEquals(250, (new SnakeDonation())->where('campaign_id', 2)->sum('amount_paid'));
    }

    /** Multi-word aggregate names must resolve as functions, not be read as a first segment. */
    public function test_multi_word_aggregate_functions_dispatch(): void
    {
        $this->assertSame('100,250', (string) (new SnakeDonation())->group_concat('amount_paid'));
        $this->assertNotNull((new SnakeDonation())->bit_or('attempts'));
        $this->assertNotNull((new SnakeDonation())->var_pop('amount_paid'));
    }

    /** The parser itself, which is where both defects lived. */
    public function test_the_aggregate_call_parser_splits_correctly(): void
    {
        $this->assertSame(['sum', 'amount_paid'], Connection::parseAggregateCall('sum_amount_paid'));
        $this->assertSame(['sum', 'campaign_id'], Connection::parseAggregateCall('sum_campaign_id'));
        $this->assertSame(['max', 'a'], Connection::parseAggregateCall('max_a'));

        // Longest-match-first: these must NOT be read as `group`, `var`, `bit`.
        $this->assertSame(['group_concat', 'amount_paid'], Connection::parseAggregateCall('group_concat_amount_paid'));
        $this->assertSame(['var_pop', 'total'], Connection::parseAggregateCall('var_pop_total'));
        $this->assertSame(['bit_and', 'flags'], Connection::parseAggregateCall('bit_and_flags'));

        // Not aggregate calls at all.
        $this->assertNull(Connection::parseAggregateCall('users_email'));
        $this->assertNull(Connection::parseAggregateCall('count'));
        $this->assertNull(Connection::parseAggregateCall('sum_'));
    }
}
