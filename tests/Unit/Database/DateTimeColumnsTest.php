<?php

namespace Eyika\Atom\Framework\Tests\Unit\Database;

use Eyika\Atom\Framework\Support\Database\Grammars\MySqlGrammar;
use Eyika\Atom\Framework\Support\Database\Grammars\PostgresGrammar;
use Eyika\Atom\Framework\Support\Database\Grammars\SqliteGrammar;
use Eyika\Atom\Framework\Support\Database\Schema\Blueprint;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * Two gaps reported by a downstream consumer, both found by a migration failing at RUN time.
 *
 * **`Blueprint` had no `date()` or `time()`.** `$table->date('started_on')` raised
 * *Call to undefined method …Blueprint::date()* — and it raised when the migration executed, not
 * when it was written, so `php -l` was clean and the file looked like every other migration. On the
 * reporting project it surfaced as several thousand errored tests at once, because the whole schema
 * is built before any of them run.
 *
 * The workaround they adopted — `char(10)` holding `Y-m-d` — is better than it sounds and worth
 * understanding, because it explains why `dateTime()` is NOT the answer: a date-only column stored
 * as a datetime holds a midnight nobody meant, and midnight in `Africa/Lagos` is the previous day
 * in UTC. For a pay date that is a silently wrong answer rather than an imprecise one.
 *
 * **`unique()` could not be named** while `index()`, `fulltext()` and `spatialIndex()` all could.
 * The generated name is `unique_<col>_<col>_…_<hash>`, which on four columns reached 69 characters
 * against MySQL's 64-character identifier limit and failed the migration with `1059`. The reason it
 * hid for days is the part worth keeping in mind: SQLite has no identifier length limit, so a suite
 * running on SQLite stays green, and an existing database never re-runs the migration that grew the
 * key — the first place it can surface is a from-scratch MySQL migrate.
 */
class DateTimeColumnsTest extends TestCase
{
    /** @return array<string, object> dialect label => grammar */
    private function grammars(): array
    {
        return [
            'mysql' => new MySqlGrammar(),
            'pgsql' => new PostgresGrammar(),
            'sqlite' => new SqliteGrammar(),
        ];
    }

    private function columns(Blueprint $table): array
    {
        $property = new ReflectionProperty(Blueprint::class, 'columns');
        $property->setAccessible(true);

        return $property->getValue($table);
    }

    private function indexes(Blueprint $table): array
    {
        $property = new ReflectionProperty(Blueprint::class, 'indexes');
        $property->setAccessible(true);

        return $property->getValue($table);
    }

    // ---------------------------------------------------------------- the column types

    public function test_a_date_column_can_be_declared(): void
    {
        $table = new Blueprint('payroll');
        $table->date('started_on');

        $this->assertNotEmpty($this->columns($table), 'date() recorded no column');
    }

    public function test_a_time_column_can_be_declared(): void
    {
        $table = new Blueprint('stores');
        $table->time('dispatch_cutoff');

        $this->assertNotEmpty($this->columns($table), 'time() recorded no column');
    }

    public function test_a_date_column_is_nullable_like_any_other(): void
    {
        $table = new Blueprint('payroll');

        // The reported call, verbatim — it is the chain that has to work, not just the method.
        $this->assertNotNull($table->date('started_on')->nullable());
    }

    /** Every dialect that ships has to know the type, or the migration fails only on that one. */
    public function test_every_dialect_maps_date_and_time(): void
    {
        foreach ($this->grammars() as $label => $grammar) {
            $map = (function () {
                return $this->typeMap();
            })->call($grammar);

            $this->assertArrayHasKey('date', $map, "[{$label}] has no date type");
            $this->assertArrayHasKey('time', $map, "[{$label}] has no time type");
            $this->assertNotSame('', $map['date'], "[{$label}] maps date to nothing");
            $this->assertNotSame('', $map['time'], "[{$label}] maps time to nothing");
        }
    }

    /** A date must not silently become a datetime — that is the timezone trap it exists to avoid. */
    public function test_a_date_is_not_mapped_to_a_datetime(): void
    {
        foreach (['mysql' => 'DATE', 'pgsql' => 'DATE'] as $label => $expected) {
            $grammar = $this->grammars()[$label];
            $map = (function () {
                return $this->typeMap();
            })->call($grammar);

            $this->assertSame($expected, $map['date'], "[{$label}] date is not a real DATE");
        }
    }

    // ---------------------------------------------------------------- naming a unique index

    public function test_a_unique_index_can_be_named(): void
    {
        $table = new Blueprint('cart_items');
        $table->unique(['cart_id', 'variant_id', 'bundle_id', 'personalisation_key'], 'cart_items_line_unique');

        $indexes = $this->indexes($table);
        $this->assertCount(1, $indexes);
        $this->assertSame('cart_items_line_unique', $indexes[0]->name);
    }

    /** Without a name the generated one still applies, so existing migrations are unaffected. */
    public function test_an_unnamed_unique_index_still_gets_a_generated_name(): void
    {
        $table = new Blueprint('cart_items');
        $table->unique(['cart_id', 'variant_id']);

        $indexes = $this->indexes($table);
        $this->assertCount(1, $indexes);
        $this->assertNotEmpty($indexes[0]->name);
    }

    /**
     * The point of the parameter: a chosen name can stay inside MySQL's 64-character identifier
     * limit where the generated one cannot.
     */
    public function test_a_chosen_name_can_fit_where_the_generated_one_cannot(): void
    {
        $columns = ['cart_id', 'variant_id', 'bundle_id', 'personalisation_key'];

        $generated = new Blueprint('cart_items');
        $generated->unique($columns);
        $generatedName = $this->indexes($generated)[0]->name;

        $named = new Blueprint('cart_items');
        $named->unique($columns, 'cart_items_line_unique');

        $this->assertGreaterThan(
            64,
            strlen($generatedName),
            'the generated name now fits, so this test no longer demonstrates the problem'
        );
        $this->assertLessThanOrEqual(64, strlen($this->indexes($named)[0]->name));
    }

    /** `unique()` was the odd one out; it must now match its neighbours. */
    public function test_unique_takes_a_name_like_every_other_index_method(): void
    {
        foreach (['index', 'unique', 'fulltext', 'spatialIndex'] as $method) {
            $parameters = (new \ReflectionMethod(Blueprint::class, $method))->getParameters();

            $this->assertGreaterThanOrEqual(
                2,
                count($parameters),
                "Blueprint::{$method}() cannot be given a name"
            );
            $this->assertSame('name', $parameters[1]->name, "Blueprint::{$method}()'s second parameter is not a name");
        }
    }
}
