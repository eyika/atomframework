<?php

namespace Eyika\Atom\Framework\Tests\Feature;

use Eyika\Atom\Framework\Support\Database\Model;

class CastWidget extends Model
{
    public $table = 'atomtest_cast_widgets';
    public $softdeletes = false;

    protected const fillable = ['id', 'meta_array', 'meta_object'];
    protected const casts = [
        'meta_array'  => 'array',
        'meta_object' => 'object',
    ];
}

/**
 * Covers BUG-22. fill() runs castAttribute() on writes as well as reads, so a JSON column's
 * payload is decoded back into PHP before it reaches the DB writer; serializeCastedValues()
 * re-encodes it just in time. That re-encoding only fired for is_array() values, but the
 * 'object' cast yields a stdClass — so an object cast reached Connection::values() unserialized.
 *
 * Nothing in the suite exercised `const casts` at all before this, which is how it survived.
 */
class CastSerializationTest extends DatabaseTestCase
{
    protected function createSchema(): void
    {
        $this->raw('DROP TABLE IF EXISTS atomtest_cast_widgets');
        $this->raw('CREATE TABLE atomtest_cast_widgets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            meta_array TEXT NULL,
            meta_object TEXT NULL,
            created_at DATETIME NULL,
            updated_at DATETIME NULL
        )');
    }

    protected function dropSchema(): void
    {
        $this->raw('DROP TABLE IF EXISTS atomtest_cast_widgets');
    }

    public function test_array_cast_round_trips_through_a_write(): void
    {
        $created = (new CastWidget())->create(['meta_array' => ['a' => 1, 'b' => [2, 3]]]);
        $this->assertNotFalse($created, 'create() with an array cast should persist');

        $read = (new CastWidget())->find($created->id);

        $this->assertSame(['a' => 1, 'b' => [2, 3]], $read->meta_array);
    }

    public function test_object_cast_round_trips_through_a_write(): void
    {
        $created = (new CastWidget())->create(['meta_object' => ['a' => 1]]);
        $this->assertNotFalse($created, 'create() with an object cast should persist');

        $read = (new CastWidget())->find($created->id);

        $this->assertIsObject($read->meta_object);
        $this->assertSame(1, $read->meta_object->a);
    }

    public function test_object_cast_survives_an_update(): void
    {
        $created = (new CastWidget())->create(['meta_object' => ['a' => 1]]);
        $this->assertNotFalse($created);

        (new CastWidget())->update(['meta_object' => ['a' => 2]], $created->id);

        $read = (new CastWidget())->find($created->id);

        $this->assertIsObject($read->meta_object);
        $this->assertSame(2, $read->meta_object->a);
    }
}
