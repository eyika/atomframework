<?php

namespace Eyika\Atom\Framework\Tests\Feature;

use Eyika\Atom\Framework\Support\Database\DB;
use Eyika\Atom\Framework\Support\Database\Model;

class IncrementCounter extends Model
{
    public $table = 'atomtest_counters';
    public $softdeletes = false;

    protected const fillable = ['id', 'name', 'hits', 'maybe_null'];
}

/**
 * Reported by a downstream consumer: `DB::table()->increment()` returns **false on success**.
 *
 * Confirmed, and the mechanism is worse than the report. The builder read the connection's result
 * backwards:
 *
 *     if (DatabaseConnection::increment(...)) { return false; }
 *     return true;
 *
 * `Connection::increment()` returned the raw `PDOStatement` from `exec()`, which is an object and
 * therefore ALWAYS truthy, and PDO runs in `ERRMODE_EXCEPTION` so a failure throws rather than
 * returning anything falsy. The `return true` was unreachable: the method returned `false` every
 * time, for every outcome.
 *
 * So the inversion was not the whole story — there was no working branch at all. A caller who
 * checked the result could only conclude the builder did not work, and would reach for
 * read-modify-write, which is the exact race `increment()` exists to prevent. That is the reporter's
 * point about why this matters more than its size suggests.
 *
 * **The model builder had the same line WITHOUT the inverted test** (`if (!…) return false;`), so
 * the two builders disagreed about the same operation by a single `!` — and its version was no more
 * informative, since the unreachable-`false` reasoning applies there too: it returned a bare `true`
 * whatever happened. Both now return the number of rows changed, matching `update()`, which is what
 * an increment compiles to.
 */
class IncrementTest extends DatabaseTestCase
{
    protected function createSchema(): void
    {
        $this->raw('DROP TABLE IF EXISTS atomtest_counters');
        $this->raw(
            'CREATE TABLE atomtest_counters ('
            . 'id INT AUTO_INCREMENT PRIMARY KEY, '
            . 'name VARCHAR(20), '
            . 'hits INT NOT NULL DEFAULT 0, '
            . 'maybe_null INT NULL'
            . ')'
        );
        $this->raw(
            "INSERT INTO atomtest_counters (id,name,hits,maybe_null) VALUES "
            . "(1,'a',5,NULL),(2,'b',5,7),(3,'shared',0,NULL),(4,'shared',0,NULL)"
        );
    }

    protected function dropSchema(): void
    {
        $this->raw('DROP TABLE IF EXISTS atomtest_counters');
    }

    private function hits(int $id): int
    {
        return (int) DB::table('atomtest_counters')->find($id)['hits'];
    }

    // ---------------------------------------------------------------- the reported bug

    /** The report, exactly: a successful increment must not report failure. */
    public function test_a_successful_increment_does_not_report_failure(): void
    {
        $result = DB::table('atomtest_counters')->where('id', 1)->increment('hits');

        $this->assertNotSame(false, $result, 'a successful increment reported false');
        $this->assertTrue((bool) $result, 'a successful increment was falsy');
    }

    public function test_increment_returns_the_number_of_rows_changed(): void
    {
        $this->assertSame(1, DB::table('atomtest_counters')->where('id', 1)->increment('hits'));
    }

    public function test_increment_actually_adds_to_the_column(): void
    {
        DB::table('atomtest_counters')->where('id', 1)->increment('hits');

        $this->assertSame(6, $this->hits(1));
    }

    public function test_decrement_returns_rows_changed_and_subtracts(): void
    {
        $this->assertSame(1, DB::table('atomtest_counters')->where('id', 2)->decrement('hits'));
        $this->assertSame(4, $this->hits(2));
    }

    public function test_a_step_other_than_one_is_honoured(): void
    {
        DB::table('atomtest_counters')->where('id', 1)->increment('hits', 10);
        $this->assertSame(15, $this->hits(1));

        DB::table('atomtest_counters')->where('id', 1)->decrement('hits', 3);
        $this->assertSame(12, $this->hits(1));
    }

    // ---------------------------------------------------------------- what the count is FOR

    /**
     * The signal the old return value could never carry: the row wasn't there.
     *
     * This is the case that makes a bare `true` useless — it is exactly what a caller needs to
     * distinguish, and not being able to is what sends them to read-modify-write.
     */
    public function test_an_increment_that_matches_nothing_returns_zero(): void
    {
        $this->assertSame(0, DB::table('atomtest_counters')->where('id', 9999)->increment('hits'));
    }

    public function test_an_increment_matching_several_rows_returns_that_count(): void
    {
        $this->assertSame(2, DB::table('atomtest_counters')->where('name', 'shared')->increment('hits'));
        $this->assertSame(1, $this->hits(3));
        $this->assertSame(1, $this->hits(4));
    }

    // ---------------------------------------------------------------- adjacent defects

    /**
     * A step of 0 emitted `SET hits = hits 0` — a SQL syntax error, not the no-op it reads as.
     *
     * The old code only prefixed a sign when `$step > 0`, so zero fell through the gap between the
     * positive and negative cases.
     */
    public function test_a_step_of_zero_is_a_no_op_rather_than_a_syntax_error(): void
    {
        $this->assertSame(0, DB::table('atomtest_counters')->where('id', 1)->increment('hits', 0));
        $this->assertSame(5, $this->hits(1));
    }

    /**
     * `NULL + 1` is NULL in SQL, so a nullable counter matches the WHERE, changes nothing, and
     * reports 0 changed. Documented rather than "fixed": silently coercing NULL to 0 would be the
     * framework inventing data.
     */
    public function test_incrementing_a_null_column_changes_nothing_and_says_so(): void
    {
        $this->assertSame(0, DB::table('atomtest_counters')->where('id', 1)->increment('maybe_null'));

        $this->assertNull(DB::table('atomtest_counters')->find(1)['maybe_null']);
    }

    // ---------------------------------------------------------------- the two builders agree

    public function test_the_model_builder_returns_rows_changed_too(): void
    {
        $this->assertSame(1, IncrementCounter::where('id', 2)->_increment('hits'));
        $this->assertSame(6, $this->hits(2));
    }

    public function test_the_model_builder_reports_zero_when_the_row_is_gone(): void
    {
        $this->assertSame(0, IncrementCounter::where('id', 9999)->_increment('hits'));
    }

    /** The divergence that produced this bug: the same operation, two builders, one answer. */
    public function test_both_builders_report_the_same_thing_for_the_same_operation(): void
    {
        $viaDb = DB::table('atomtest_counters')->where('id', 1)->increment('hits');
        $viaModel = IncrementCounter::where('id', 1)->_increment('hits');

        $this->assertSame($viaDb, $viaModel);
        $this->assertSame(7, $this->hits(1));
    }

    // ---------------------------------------------------------------- the point of the method

    /**
     * The increment is computed by the database, so two increments from the same STALE starting
     * value still both land. Read-modify-write against `hits = 5` twice would finish at 6; this
     * finishes at 7, which is the whole reason to call it.
     */
    public function test_increments_accumulate_rather_than_overwrite(): void
    {
        $stale = $this->hits(1);
        $this->assertSame(5, $stale);

        DB::table('atomtest_counters')->where('id', 1)->increment('hits');
        DB::table('atomtest_counters')->where('id', 1)->increment('hits');

        $this->assertSame(7, $this->hits(1));
        $this->assertNotSame($stale + 1, $this->hits(1), 'the second increment overwrote the first');
    }
}
