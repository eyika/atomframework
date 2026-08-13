<?php

namespace Eyika\Atom\Framework\Tests\Unit\Database;

use Eyika\Atom\Framework\Support\Cache\ArrayCache;
use Eyika\Atom\Framework\Support\Cache\CacheItem;
use Eyika\Atom\Framework\Support\Database\Connection;
use Eyika\Atom\Framework\Support\Database\DB;
use Eyika\Atom\Framework\Support\Database\Schema\Blueprint;
use Eyika\Atom\Framework\Support\Facade\DatabaseConnection;
use Eyika\Atom\Framework\Support\Validator;
use InvalidArgumentException;
use PDO;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * A sweep of the gaps an app team had logged against the framework but which were never fixed.
 * Each was re-validated against current code before being touched — one entry in that log
 * (`Model::…->count()` missing) proved stale and needed no change.
 */
class DocsGapSweepTest extends TestCase
{
    private Connection $conn;

    protected function setUp(): void
    {
        parent::setUp();

        if (!extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite is not enabled.');
        }

        $this->conn = new Connection([
            'default'     => 'sqlite',
            'connections' => ['sqlite' => ['database' => ':memory:']],
        ]);
        $this->conn->connect();
        DatabaseConnection::swap($this->conn);

        DatabaseConnection::exec('CREATE TABLE ledger (id INTEGER PRIMARY KEY, business_id INT, amount INT)');
        DatabaseConnection::exec('INSERT INTO ledger (business_id, amount) VALUES (1, 10), (1, 5), (2, 7)');
    }

    // ---------------------------------------------------------------- raw SELECT bindings

    /**
     * `DB::select($sql, $bindings)` took ONE parameter, so PHP discarded the bindings silently
     * and the query ran with an unbound placeholder — returning a plausible EMPTY result instead
     * of throwing. That made string interpolation look like the only thing that worked, i.e. the
     * method actively pushed callers toward an injection hole.
     */
    public function test_raw_select_honours_positional_bindings(): void
    {
        $rows = DB::select('SELECT COUNT(*) AS n FROM ledger WHERE business_id = ?', [1]);

        $this->assertSame(2, (int) $rows[0]['n']);
    }

    public function test_raw_select_honours_named_bindings(): void
    {
        $rows = DB::select('SELECT COUNT(*) AS n FROM ledger WHERE business_id = :b', ['b' => 2]);

        $this->assertSame(1, (int) $rows[0]['n']);
    }

    public function test_raw_select_bindings_are_bound_not_interpolated(): void
    {
        // A value that would change the query's meaning if it were pasted in.
        $rows = DB::select('SELECT COUNT(*) AS n FROM ledger WHERE business_id = ?', ['1 OR 1=1']);

        $this->assertSame(0, (int) $rows[0]['n'], 'the binding was interpolated rather than bound');
    }

    public function test_raw_select_without_bindings_still_works(): void
    {
        $this->assertSame(3, (int) DB::select('SELECT COUNT(*) AS n FROM ledger')[0]['n']);
    }

    // ---------------------------------------------------------------- selectRaw parity

    /** `groupBy()`/`having()` were on both builders but `selectRaw()` only on the model builder. */
    public function test_db_table_supports_select_raw_with_group_by(): void
    {
        $rows = DB::table('ledger')
            ->selectRaw('business_id')
            ->selectRaw('SUM(amount) AS total')
            ->groupBy('business_id')
            ->get();

        $totals = [];
        foreach ($rows as $row) {
            $totals[(int) $row['business_id']] = (int) $row['total'];
        }

        $this->assertSame([1 => 15, 2 => 7], $totals);
    }

    public function test_select_raw_does_not_leak_between_queries(): void
    {
        $builder = DB::table('ledger');
        $builder->selectRaw('SUM(amount) AS total')->get();

        // The builder resets after a fetch; a plain read must not still carry the aggregate.
        $rows = DB::table('ledger')->where('business_id', 2)->get();

        $this->assertArrayHasKey('amount', (array) $rows[0]);
    }

    // ---------------------------------------------------------------- PDO options from config

    /**
     * `getOptions()` never merged the connection's own `options` map, and the scaffolded config
     * ships `PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA')` — so a deployment that set that
     * variable believed it had TLS to its database and did not.
     */
    public function test_configured_pdo_options_are_merged(): void
    {
        $conn = new Connection([
            'default'     => 'sqlite',
            'connections' => ['sqlite' => [
                'database' => ':memory:',
                'options'  => [PDO::ATTR_CASE => PDO::CASE_UPPER],
            ]],
        ]);

        $this->assertSame(PDO::CASE_UPPER, $this->options($conn)[PDO::ATTR_CASE] ?? null);
    }

    /** An explicitly configured option beats the framework default, or configuring it is pointless. */
    public function test_a_configured_option_overrides_the_default(): void
    {
        $conn = new Connection([
            'default'     => 'sqlite',
            'connections' => ['sqlite' => [
                'database' => ':memory:',
                'options'  => [PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ],
            ]],
        ]);

        $this->assertSame(PDO::FETCH_OBJ, $this->options($conn)[PDO::ATTR_DEFAULT_FETCH_MODE]);
    }

    /**
     * The scaffolded SSL_CA key is null whenever its env var is unset, so passing it through
     * would break the connection for every deployment that has simply never configured TLS.
     */
    public function test_null_configured_options_are_dropped(): void
    {
        $conn = new Connection([
            'default'     => 'sqlite',
            'connections' => ['sqlite' => [
                'database' => ':memory:',
                'options'  => [PDO::MYSQL_ATTR_SSL_CA => null],
            ]],
        ]);

        $this->assertArrayNotHasKey(PDO::MYSQL_ATTR_SSL_CA, $this->options($conn));
    }

    public function test_defaults_survive_when_no_options_are_configured(): void
    {
        $options = $this->options($this->conn);

        $this->assertSame(PDO::ERRMODE_EXCEPTION, $options[PDO::ATTR_ERRMODE]);
        $this->assertFalse($options[PDO::ATTR_EMULATE_PREPARES]);
    }

    private function options(Connection $conn): array
    {
        $method = (new ReflectionClass(Connection::class))->getMethod('getOptions');
        $method->setAccessible(true);

        return $method->invoke($conn);
    }

    // ---------------------------------------------------------------- narrow integer columns

    /**
     * `tinyText`/`mediumText`/`longText` and the blob family all existed, so the missing integer
     * widths read as an oversight — and the failure only surfaced when the migration ran.
     */
    public function test_narrow_integer_columns_compile_and_run(): void
    {
        $blueprint = new Blueprint('gauges');
        $blueprint->integer('id');
        $blueprint->unsignedTinyInteger('attempts');
        $blueprint->smallInteger('offset_minutes');
        $blueprint->mediumInteger('population');

        foreach ($blueprint->compile() as $sql) {
            DatabaseConnection::exec($sql);
        }

        DatabaseConnection::exec('INSERT INTO gauges (id, attempts, offset_minutes, population) VALUES (1, 3, -60, 70000)');
        $row = DB::select('SELECT * FROM gauges')[0];

        $this->assertSame(3, (int) $row['attempts']);
        $this->assertSame(-60, (int) $row['offset_minutes']);
        $this->assertSame(70000, (int) $row['population']);
    }

    public function test_mysql_emits_the_narrow_types_rather_than_widening_them(): void
    {
        $grammar = new \Eyika\Atom\Framework\Support\Database\Grammars\MySqlGrammar();

        $blueprint = new Blueprint('gauges');
        $blueprint->unsignedTinyInteger('attempts');
        $blueprint->smallInteger('offset_minutes');
        $blueprint->mediumInteger('population');

        // compile() resolves the grammar off the bound connection (SQLite here), so go through
        // the MySQL grammar directly to assert the widths it actually emits.
        $sql = implode(' ', $grammar->compileCreate($blueprint));

        $this->assertStringContainsString('TINYINT', $sql);
        $this->assertStringContainsString('SMALLINT', $sql);
        $this->assertStringContainsString('MEDIUMINT', $sql);
    }

    // ---------------------------------------------------------------- array cache

    /**
     * `save()` stores a plain array but `hasItem()` called `getExpiration()` on it, so the array
     * driver fataled on every stored key — while `getItem()` right below read `expires_at`
     * correctly, which is why the two disagreed.
     */
    public function test_array_cache_reports_a_stored_key(): void
    {
        $cache = new ArrayCache();
        $cache->save(new CacheItem('k', 'v', true));

        $this->assertTrue($cache->hasItem('k'));
        $this->assertSame('v', $cache->getItem('k')->get());
    }

    public function test_array_cache_reports_a_missing_key_without_erroring(): void
    {
        $cache = new ArrayCache();

        $this->assertFalse($cache->hasItem('absent'));

        $item = $cache->getItem('absent');
        $this->assertFalse($item->isHit());
        $this->assertNull($item->get());
    }

    public function test_array_cache_deletes_a_key(): void
    {
        $cache = new ArrayCache();
        $cache->save(new CacheItem('k', 'v', true));
        $cache->deleteItem('k');

        $this->assertFalse($cache->hasItem('k'));
    }

    // ---------------------------------------------------------------- validation rules

    /**
     * An unrecognised rule returned '' — i.e. "valid" — so `nullable|string` looked like it
     * worked while doing nothing, and a typo silently disabled that rule. A rule that no longer
     * runs is a hole no test will catch.
     */
    public function test_an_unknown_validation_rule_is_a_loud_error(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown validation rule [strng]');

        Validator::validate(['name' => 'x'], ['name' => 'strng']);
    }

    /** `nullable` is accepted by name — it is what a Laravel-trained developer reaches for first. */
    public function test_nullable_is_recognised_and_harmless(): void
    {
        $this->assertNotFalse(Validator::validate(['bio' => null], ['bio' => 'nullable|string']));
        $this->assertNotFalse(Validator::validate(['bio' => 'hi'], ['bio' => 'nullable|string']));
    }

    /**
     * Making unknown rules throw would otherwise turn the most commonly reached-for Laravel rule
     * names into hard upgrade failures, so these three are implemented rather than rejected.
     * They were silent no-ops before — `date` validated nothing at all.
     */
    public function test_alpha_alpha_num_and_date_are_real_rules_now(): void
    {
        $this->assertNotFalse(Validator::validate(['v' => 'abc'], ['v' => 'alpha']));
        $this->assertFalse(Validator::validate(['v' => 'ab1'], ['v' => 'alpha']));

        $this->assertNotFalse(Validator::validate(['v' => 'ab1'], ['v' => 'alpha_num']));
        $this->assertFalse(Validator::validate(['v' => 'ab-1'], ['v' => 'alpha_num']));

        $this->assertNotFalse(Validator::validate(['v' => '2026-08-06'], ['v' => 'date']));
        $this->assertFalse(Validator::validate(['v' => 'not a date'], ['v' => 'date']));
    }

    /** The rule names an app is most likely to already be using must not become upgrade failures. */
    public function test_the_common_rule_vocabulary_is_accepted(): void
    {
        foreach (['required', 'sometimes', 'forbidden', 'string', 'integer', 'numeric', 'boolean',
                  'array', 'json', 'email', 'url', 'uuid', 'ascii', 'max:5', 'min:1', 'in:a,b'] as $rule) {
            Validator::$errors = [];
            Validator::validate(['f' => 'a'], ['f' => $rule]);
            $this->addToAssertionCount(1); // reaching here means the rule name was recognised
        }
    }

    public function test_known_rules_still_validate(): void
    {
        $this->assertFalse(Validator::validate(['age' => 'abc'], ['age' => 'integer']));
        $this->assertNotFalse(Validator::validate(['age' => '42'], ['age' => 'integer']));
    }

    /**
     * The truthiness trap: against an all-optional rule set an empty body validates SUCCESSFULLY
     * and returns `[]`, which is falsy — so `if (!$input = Validator::validate(...))` reports a
     * failure on a request that passed.
     */
    public function test_passes_reports_success_where_truthiness_would_report_failure(): void
    {
        $result = Validator::validate([], ['note' => 'sometimes|string']);

        $this->assertSame([], $result, 'an all-optional rule set over an empty body passes');
        $this->assertFalse((bool) $result, 'and its result is falsy — this is the trap');

        $this->assertTrue(Validator::passes([], ['note' => 'sometimes|string']));
        $this->assertFalse(Validator::fails([], ['note' => 'sometimes|string']));
    }

    public function test_fails_reports_a_real_failure(): void
    {
        $this->assertTrue(Validator::fails(['age' => 'abc'], ['age' => 'integer']));
        $this->assertFalse(Validator::passes(['age' => 'abc'], ['age' => 'integer']));
    }
}
