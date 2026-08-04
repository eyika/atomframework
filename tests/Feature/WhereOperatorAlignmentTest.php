<?php

namespace Eyika\Atom\Framework\Tests\Feature;

use Eyika\Atom\Framework\Support\Collections\Collection;
use Eyika\Atom\Framework\Support\Database\Model;

class AlignWidget extends Model
{
    public $table = 'atomtest_align';
    public $softdeletes = false;

    protected const fillable = ['id', 'sku', 'locale', 'qty'];
}

/**
 * Reported by Claude C (vendra): `whereIn(...)->where(...)` produced invalid SQL —
 * `SQLSTATE[HY000]: General error: 1 near ":locale": syntax error` — while the reverse order
 * worked, and each clause alone worked.
 *
 * Cause: Connection::filter() advances its index into the $operators array only when
 * condition() sets $incr_operator, and condition() set it FALSE for the IN branch. Since
 * QueryBuilder::__where() pushes exactly one operator per condition (including 'IN'), the index
 * stopped tracking the conditions: the clause after an IN re-read the IN operator and emitted
 * `` `locale` IN :locale `` — hence the error pointing at the placeholder rather than the IN.
 *
 * The LIKE branch had the identical defect, so whereLike() poisoned the following clause too.
 * IS NULL is correctly false — __where() short-circuits there without pushing an operator.
 */
class WhereOperatorAlignmentTest extends DatabaseTestCase
{
    protected function createSchema(): void
    {
        $this->raw('DROP TABLE IF EXISTS atomtest_align');
        $this->raw('CREATE TABLE atomtest_align (
            id INT AUTO_INCREMENT PRIMARY KEY,
            sku VARCHAR(20) NULL,
            locale VARCHAR(10) NULL,
            qty INT NULL,
            created_at DATETIME NULL,
            updated_at DATETIME NULL
        )');
        // 'en-GB' exists so an exact `= 'en'` and a leaked `LIKE '%en%'` give DIFFERENT answers —
        // without it the LIKE misalignment produces valid SQL that coincidentally matches, and
        // the bug passes unnoticed.
        $this->raw("INSERT INTO atomtest_align (sku, locale, qty) VALUES
            ('a','en',1), ('b','en',2), ('c','fr',3), ('d','es',4), ('e',NULL,5), ('f','en-GB',6)");
    }

    protected function dropSchema(): void
    {
        $this->raw('DROP TABLE IF EXISTS atomtest_align');
    }

    /** @return array<int, mixed> */
    private function rows(mixed $r): array
    {
        return $r instanceof Collection ? $r->all() : (is_array($r) ? $r : []);
    }

    private function skus(mixed $r): array
    {
        $s = array_map(fn ($x) => $x->sku, $this->rows($r));
        sort($s);
        return $s;
    }

    /** The exact reported failure. */
    public function test_where_in_followed_by_where(): void
    {
        $r = (new AlignWidget())->whereIn('sku', ['a', 'b', 'c'])->where('locale', 'en')->get();

        $this->assertSame(['a', 'b'], $this->skus($r));
    }

    /** Reported as working — must stay working. */
    public function test_where_followed_by_where_in(): void
    {
        $r = (new AlignWidget())->where('locale', 'en')->whereIn('sku', ['a', 'b', 'c'])->get();

        $this->assertSame(['a', 'b'], $this->skus($r));
    }

    public function test_where_not_in_followed_by_where(): void
    {
        $r = (new AlignWidget())->whereNotIn('sku', ['c', 'd', 'e'])->where('locale', 'en')->get();

        $this->assertSame(['a', 'b'], $this->skus($r));
    }

    /**
     * The same defect in the LIKE branch — not in the report, found by inspection, and NASTIER:
     * a leaked LIKE operator yields VALID SQL (`LOWER(locale) LIKE LOWER('%en%')`), so instead of
     * erroring it silently returns the wrong rows. 'en-GB' is what exposes it — an exact match
     * must not pick it up.
     */
    public function test_where_like_followed_by_where_uses_equality_not_like(): void
    {
        $r = (new AlignWidget())->whereLike('sku', 'a')->where('locale', 'en')->get();

        $this->assertSame(['a'], $this->skus($r));
    }

    public function test_a_leaked_like_operator_does_not_broaden_a_later_equality(): void
    {
        // sku LIKE '%a%' matches only 'a'; locale = 'en' must be exact, excluding 'en-GB'.
        $r = (new AlignWidget())->whereLike('locale', 'en')->where('qty', 1)->get();
        $this->assertSame(['a'], $this->skus($r));

        // And the plain equality after a LIKE must not itself become a LIKE.
        $exact = (new AlignWidget())->whereIn('sku', ['a', 'b', 'f'])->where('locale', 'en')->get();
        $this->assertSame(['a', 'b'], $this->skus($exact), "'en-GB' must not match an exact 'en'");
    }

    public function test_where_in_between_two_plain_wheres(): void
    {
        $r = (new AlignWidget())
            ->where('locale', 'en')
            ->whereIn('sku', ['a', 'b', 'c'])
            ->where('qty', 2)
            ->get();

        $this->assertSame(['b'], $this->skus($r));
    }

    public function test_two_where_ins_then_a_where(): void
    {
        $r = (new AlignWidget())
            ->whereIn('sku', ['a', 'b', 'c'])
            ->whereIn('locale', ['en', 'fr'])
            ->where('qty', 3)
            ->get();

        $this->assertSame(['c'], $this->skus($r));
    }

    /**
     * IS NULL genuinely consumes no operator slot, so it must NOT advance the index — the
     * inverse mistake would misalign everything after it.
     */
    public function test_where_null_followed_by_where_still_aligns(): void
    {
        $r = (new AlignWidget())->whereNull('locale')->where('qty', 5)->get();

        $this->assertSame(['e'], $this->skus($r));
    }

    public function test_where_in_followed_by_a_comparison_operator(): void
    {
        $r = (new AlignWidget())->whereIn('sku', ['a', 'b', 'c'])->where('qty', '>', 1)->get();

        $this->assertSame(['b', 'c'], $this->skus($r));
    }
}
