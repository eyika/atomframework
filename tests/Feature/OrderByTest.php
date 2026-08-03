<?php

namespace Eyika\Atom\Framework\Tests\Feature;

use Eyika\Atom\Framework\Support\Database\DB;
use Eyika\Atom\Framework\Support\Database\Model;

class SortWidget extends Model
{
    public $table = 'atomtest_sort_widgets';
    public $softdeletes = false;

    protected const fillable = ['id', 'is_default', 'currency'];
}

/**
 * Reported by Claude C (vendra): orderBy() assigned bind_or_filter['ORDER BY'] wholesale, so a
 * second call REPLACED the first — orderBy('is_default','DESC')->orderBy('currency') silently
 * sorted by currency alone, with no error. The direction was also appended once after the whole
 * comma list, so orderBy('a,b','DESC') emitted `ORDER BY a, b DESC` (a ascending).
 *
 * Both builders are covered: the model query builder and the static DB builder, which carried an
 * identical copy of the bug.
 */
class OrderByTest extends DatabaseTestCase
{
    protected function createSchema(): void
    {
        $this->raw('DROP TABLE IF EXISTS atomtest_sort_widgets');
        $this->raw('CREATE TABLE atomtest_sort_widgets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            is_default TINYINT(1) NOT NULL,
            currency VARCHAR(10) NOT NULL,
            created_at DATETIME NULL,
            updated_at DATETIME NULL
        )');
        // Deliberately inserted so that id order != the expected sort order.
        $this->raw("INSERT INTO atomtest_sort_widgets (is_default, currency) VALUES
            (0, 'AAA'), (1, 'ZZZ'), (0, 'BBB'), (1, 'MMM')");
    }

    protected function dropSchema(): void
    {
        $this->raw('DROP TABLE IF EXISTS atomtest_sort_widgets');
    }

    public function test_successive_order_by_calls_accumulate(): void
    {
        $rows = (new SortWidget())
            ->orderBy('is_default', 'DESC')
            ->orderBy('currency', 'ASC')
            ->get(false);

        $actual = array_map(
            fn ($r) => $r->is_default . ':' . $r->currency,
            is_array($rows) ? $rows : $rows->all()
        );

        // is_default DESC first, then currency ASC within each group.
        $this->assertSame(['1:MMM', '1:ZZZ', '0:AAA', '0:BBB'], $actual);
    }

    public function test_a_comma_list_applies_the_direction_to_every_column(): void
    {
        $rows = (new SortWidget())->orderBy('is_default,currency', 'DESC')->get(false);

        $actual = array_map(
            fn ($r) => $r->is_default . ':' . $r->currency,
            is_array($rows) ? $rows : $rows->all()
        );

        // Both DESC — previously only the last column got DESC.
        $this->assertSame(['1:ZZZ', '1:MMM', '0:BBB', '0:AAA'], $actual);
    }

    public function test_static_db_builder_accumulates_too(): void
    {
        $rows = DB::table('atomtest_sort_widgets')
            ->orderBy('is_default', 'DESC')
            ->orderBy('currency', 'ASC')
            ->get();

        $actual = array_map(fn ($r) => $r['is_default'] . ':' . $r['currency'], $rows);

        $this->assertSame(['1:MMM', '1:ZZZ', '0:AAA', '0:BBB'], $actual);
    }

    public function test_a_single_order_by_is_unchanged(): void
    {
        $rows = (new SortWidget())->orderBy('currency', 'ASC')->get(false);

        $actual = array_map(
            fn ($r) => $r->currency,
            is_array($rows) ? $rows : $rows->all()
        );

        $this->assertSame(['AAA', 'BBB', 'MMM', 'ZZZ'], $actual);
    }
}
