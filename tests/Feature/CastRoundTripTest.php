<?php

namespace Eyika\Atom\Framework\Tests\Feature;

use Eyika\Atom\Framework\Support\Database\DB;
use Eyika\Atom\Framework\Support\Database\Model;
use PHPUnit\Framework\Attributes\DataProvider;

class RoundTripWidget extends Model
{
    public $table = 'atomtest_roundtrip';
    public $softdeletes = false;

    protected const fillable = ['id', 'meta_array', 'meta_alt', 'meta_object', 'flag', 'count'];
    protected const casts = [
        'meta_array'  => 'array',
        'meta_alt'   => 'json',
        'meta_object' => 'object',
        'flag'        => 'boolean',
        'count'       => 'integer',
    ];
}

/**
 * DECISION HARNESS for PERF-08.
 *
 * `fill()` runs `castAttribute()` on writes as well as reads, so a JSON payload is decoded into
 * PHP on the way IN, and `serializeCastedValues()` re-encodes it just before the DB write.
 * PERF-08 proposes skipping the write-side cast to avoid that decode/re-encode round trip — but
 * it was deferred because "short-circuiting risks corrupting every JSON write", with no evidence
 * either way.
 *
 * This pins the observable contract so that question is answerable by running the suite rather
 * than by reasoning. It asserts three things per case, which is what makes it decisive:
 *
 *   1. the RAW stored column, read through DB::table() so no cast runs — this is where
 *      double-encoding shows up (a JSON string stored as a JSON string containing JSON);
 *   2. the value read back through the model, i.e. what application code sees;
 *   3. the in-memory model immediately after the write, since the write-side cast is what
 *      currently normalises `$model->meta_array` to an array rather than the string passed in.
 *
 * Point 3 is the subtle one: skipping the write cast would leave a model's property holding
 * whatever the caller passed, so `create(['meta_array' => '{"a":1}'])->meta_array` would be a
 * string where it is an array today. That is a behaviour change no amount of DB inspection
 * catches, and it is the reason this harness asserts it explicitly.
 *
 * Every write path is covered because they differ: create() and save() go through _save(), while
 * update()/updateOrCreate() go through __update() — two separate re-encode call sites.
 */
class CastRoundTripTest extends DatabaseTestCase
{
    protected function createSchema(): void
    {
        $this->raw('DROP TABLE IF EXISTS atomtest_roundtrip');
        $this->raw('CREATE TABLE atomtest_roundtrip (
            id INT AUTO_INCREMENT PRIMARY KEY,
            meta_array TEXT NULL,
            meta_alt TEXT NULL,
            meta_object TEXT NULL,
            flag TINYINT(1) NULL,
            count INT NULL,
            created_at DATETIME NULL,
            updated_at DATETIME NULL
        )');
    }

    protected function dropSchema(): void
    {
        $this->raw('DROP TABLE IF EXISTS atomtest_roundtrip');
    }

    /** The column exactly as stored, with no model casting in the way. */
    private function rawColumn(int $id, string $column): mixed
    {
        $row = DB::table('atomtest_roundtrip')->where('id', $id)->first();

        return is_array($row) ? ($row[$column] ?? null) : null;
    }

    private function readBack(int $id): RoundTripWidget
    {
        return (new RoundTripWidget())->find($id);
    }

    // ---------------------------------------------------------------- array / json casts

    /**
     * @return array<string, array{0: mixed, 1: array}>  [input, expected decoded value]
     */
    public static function arrayPayloads(): array
    {
        return [
            'flat array'        => [['a' => 1, 'b' => 2], ['a' => 1, 'b' => 2]],
            'nested array'      => [['a' => ['b' => ['c' => 3]]], ['a' => ['b' => ['c' => 3]]]],
            'list'              => [[1, 2, 3], [1, 2, 3]],
            'empty array'       => [[], []],
            'unicode'           => [['name' => 'Ábdulbasit — ₦'], ['name' => 'Ábdulbasit — ₦']],
            'quotes and slashes' => [['s' => 'a "quoted" \\ value'], ['s' => 'a "quoted" \\ value']],
            'numeric strings'   => [['n' => '007'], ['n' => '007']],
            'null member'       => [['n' => null], ['n' => null]],
            'bool members'      => [['t' => true, 'f' => false], ['t' => true, 'f' => false]],
        ];
    }

    #[DataProvider('arrayPayloads')]
    public function test_array_cast_round_trips_through_create(mixed $input, array $expected): void
    {
        $created = (new RoundTripWidget())->create(['meta_array' => $input]);
        $this->assertNotFalse($created);

        // (3) in-memory shape straight after the write
        $this->assertSame($expected, $created->meta_array, 'in-memory value after create()');

        // (1) raw storage — must be valid JSON of the value, NOT a JSON-encoded JSON string
        $raw = $this->rawColumn($created->id, 'meta_array');
        $this->assertIsString($raw, 'the column must hold a JSON string');
        $this->assertSame($expected, json_decode($raw, true), 'raw stored JSON');

        // (2) what application code reads back
        $this->assertSame($expected, $this->readBack($created->id)->meta_array, 'value read back');
    }

    /**
     * A JSON *string* input is the case PERF-08 would change: today the write-side cast decodes
     * it and serializeCastedValues() re-encodes, normalising it. Skipping the cast would store
     * the caller's string verbatim.
     */
    public function test_a_json_string_input_is_stored_as_json_not_double_encoded(): void
    {
        $created = (new RoundTripWidget())->create(['meta_array' => '{"a":1,"b":[2,3]}']);
        $this->assertNotFalse($created);

        $raw = $this->rawColumn($created->id, 'meta_array');

        $this->assertSame(
            ['a' => 1, 'b' => [2, 3]],
            json_decode($raw, true),
            'a JSON string input must land as that JSON, not as a quoted string containing it'
        );

        // Double-encoding would make the decoded value a string rather than an array.
        $this->assertIsArray(json_decode($raw, true));

        $this->assertSame(['a' => 1, 'b' => [2, 3]], $this->readBack($created->id)->meta_array);
    }

    /**
     * The 'json' cast must behave like 'array'. NOTE: the column is deliberately NOT named
     * `*_json` — `Connection::fetch()` auto-decodes any column whose name contains `_json`
     * (PERF-09), which would decode the "raw" read and hide a double-encoding bug.
     */
    /**
     * A malformed JSON string is where the current implementation and the PERF-08 short-circuit
     * genuinely diverge, so it is pinned here rather than left to chance.
     *
     * Today the write-side cast decodes it — `json_decode` yields null — and the column is stored
     * NULL: the bad input is swallowed. Skipping the write cast would instead store the malformed
     * string verbatim, which then fails to decode on read. Both are poor; they are poor in
     * different ways, and changing which one happens is a behaviour change either way.
     */
    public function test_a_malformed_json_string_is_currently_swallowed_to_null(): void
    {
        $created = (new RoundTripWidget())->create(['meta_array' => '{not valid json', 'count' => 1]);
        $this->assertNotFalse($created);

        $this->assertNull(
            $this->rawColumn($created->id, 'meta_array'),
            'current behaviour: malformed JSON decodes to null and is stored as NULL'
        );
        $this->assertNull($this->readBack($created->id)->meta_array);
    }

    public function test_json_cast_behaves_like_array_cast(): void
    {
        $created = (new RoundTripWidget())->create(['meta_alt' => ['x' => ['y' => 1]]]);
        $this->assertNotFalse($created);

        $this->assertSame(['x' => ['y' => 1]], json_decode($this->rawColumn($created->id, 'meta_alt'), true));
        $this->assertSame(['x' => ['y' => 1]], $this->readBack($created->id)->meta_alt);
    }

    // ---------------------------------------------------------------- object cast

    public function test_object_cast_round_trips(): void
    {
        $created = (new RoundTripWidget())->create(['meta_object' => ['a' => 1]]);
        $this->assertNotFalse($created);

        $this->assertSame(['a' => 1], json_decode($this->rawColumn($created->id, 'meta_object'), true));

        $read = $this->readBack($created->id);
        $this->assertIsObject($read->meta_object);
        $this->assertSame(1, $read->meta_object->a);
    }

    // ---------------------------------------------------------------- update paths

    /** update() goes through __update(), a different re-encode call site than _save(). */
    public function test_array_cast_round_trips_through_update(): void
    {
        $created = (new RoundTripWidget())->create(['meta_array' => ['v' => 1]]);
        $this->assertNotFalse($created);

        (new RoundTripWidget())->update(['meta_array' => ['v' => 2, 'nested' => ['deep' => true]]], $created->id);

        $raw = $this->rawColumn($created->id, 'meta_array');
        $this->assertSame(['v' => 2, 'nested' => ['deep' => true]], json_decode($raw, true));
        $this->assertSame(['v' => 2, 'nested' => ['deep' => true]], $this->readBack($created->id)->meta_array);
    }

    public function test_repeated_updates_do_not_accumulate_encoding(): void
    {
        $created = (new RoundTripWidget())->create(['meta_array' => ['n' => 0]]);
        $this->assertNotFalse($created);

        for ($i = 1; $i <= 3; $i++) {
            (new RoundTripWidget())->update(['meta_array' => ['n' => $i]], $created->id);
        }

        $raw = $this->rawColumn($created->id, 'meta_array');

        // Each pass must re-encode exactly once. Accumulating encodings would make the decoded
        // value a string (then a string of a string), never an array.
        $this->assertIsArray(json_decode($raw, true), 'encoding must not accumulate across writes');
        $this->assertSame(['n' => 3], json_decode($raw, true));
    }

    /**
     * A companion non-null column is present deliberately: creating a row whose values are ALL
     * null emits invalid SQL (an empty insert list) — a separate edge case, not the behaviour
     * under test here.
     */
    public function test_null_is_stored_as_null_not_encoded(): void
    {
        $created = (new RoundTripWidget())->create(['meta_array' => null, 'count' => 1]);
        $this->assertNotFalse($created);

        $this->assertNull($this->rawColumn($created->id, 'meta_array'), 'null must not become "null"');
        $this->assertNull($this->readBack($created->id)->meta_array);
    }

    // ---------------------------------------------------------------- scalar casts

    public function test_scalar_casts_round_trip(): void
    {
        $created = (new RoundTripWidget())->create([
            'meta_array' => ['x' => 1],
            'flag'       => '1',
            'count'      => '42',
        ]);
        $this->assertNotFalse($created);

        // The write-side cast is what normalises these in memory today.
        $this->assertTrue($created->flag, 'boolean cast applied on write');
        $this->assertSame(42, $created->count, 'integer cast applied on write');

        $read = $this->readBack($created->id);
        $this->assertTrue($read->flag);
        $this->assertSame(42, $read->count);
    }
}
