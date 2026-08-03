<?php

namespace Eyika\Atom\Framework\Tests\Feature;

use Eyika\Atom\Framework\Support\Collections\Collection;
use Eyika\Atom\Framework\Support\Database\Model;

class StaticCallWidget extends Model
{
    public $table = 'atomtest_static_call';
    public $softdeletes = false;

    protected const fillable = ['id', 'name', 'score'];
}

/**
 * Reported by Claude A: `Model::orderBy('name')->get()` raised a raw PHP
 * "Non-static method … cannot be called statically" Error rather than the framework's own
 * "not supported by dynamic static calls" message — because `orderBy()` was a plain public
 * method, so PHP resolved it directly and never consulted __callStatic. Whitelisting alone
 * could not have fixed it; the method had to become `_orderBy()`.
 *
 * The reflection guards in DynamicMethodResolutionTest enforce the naming invariant. These
 * exercise the calls end to end, which is what actually proves the dispatch works.
 */
class StaticBuilderCallTest extends DatabaseTestCase
{
    protected function createSchema(): void
    {
        $this->raw('DROP TABLE IF EXISTS atomtest_static_call');
        $this->raw('CREATE TABLE atomtest_static_call (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(50) NULL,
            score INT NULL,
            created_at DATETIME NULL,
            updated_at DATETIME NULL
        )');
        $this->raw("INSERT INTO atomtest_static_call (name, score) VALUES ('b', 2), ('a', 1), ('c', 3)");
    }

    protected function dropSchema(): void
    {
        $this->raw('DROP TABLE IF EXISTS atomtest_static_call');
    }

    /** @return array<int, mixed> */
    private function rows(mixed $result): array
    {
        return $result instanceof Collection ? $result->all() : (array) $result;
    }

    /** The exact call from the report. */
    public function test_order_by_is_callable_statically(): void
    {
        $rows = $this->rows(StaticCallWidget::orderBy('name', 'ASC')->get());

        $this->assertSame(['a', 'b', 'c'], array_map(fn ($r) => $r->name, $rows));
    }

    public function test_order_by_desc_statically(): void
    {
        $rows = $this->rows(StaticCallWidget::orderBy('score', 'DESC')->get());

        $this->assertSame([3, 2, 1], array_map(fn ($r) => (int) $r->score, $rows));
    }

    /** Static entry point then instance chaining — the mixed form apps actually write. */
    public function test_static_entry_point_chains_onto_instance_methods(): void
    {
        $rows = $this->rows(StaticCallWidget::where('score', '>', 1)->orderBy('score', 'ASC')->get());

        $this->assertSame([2, 3], array_map(fn ($r) => (int) $r->score, $rows));
    }

    public function test_the_other_newly_exposed_names_dispatch(): void
    {
        // firstOrNew and the missing or-variants were whitelist-only gaps.
        $this->assertNotNull(StaticCallWidget::firstOrNew(['name' => 'a']));
        $this->assertCount(2, $this->rows(StaticCallWidget::whereIn('name', ['a', 'b'])->get()));
        $this->assertCount(2, $this->rows(StaticCallWidget::orWhereIn('name', ['a', 'b'])->get()));
        $this->assertCount(1, $this->rows(StaticCallWidget::whereGreaterThan('score', 2)->get()));
    }

    /**
     * A leading OR has nothing to OR against, so it degrades to a plain AND rather than
     * erroring or dropping the condition. Pinned because it is the one thing about exposing
     * the or-variants statically that could surprise someone.
     */
    public function test_a_leading_or_condition_degrades_to_and(): void
    {
        $this->assertSame(
            array_map(fn ($r) => $r->name, $this->rows(StaticCallWidget::where('name', 'a')->get())),
            array_map(fn ($r) => $r->name, $this->rows(StaticCallWidget::orWhere('name', 'a')->get()))
        );
    }

    /** An unknown name must still give the framework's message, not a raw PHP Error. */
    public function test_an_unknown_static_method_reports_the_framework_message(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('not supported by dynamic static calls');

        StaticCallWidget::noSuchBuilderMethod();
    }
}
