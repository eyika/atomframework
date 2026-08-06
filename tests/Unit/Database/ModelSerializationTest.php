<?php

namespace Eyika\Atom\Framework\Tests\Unit\Database;

use ArrayIterator;
use Eyika\Atom\Framework\Support\Collections\Collection;
use Eyika\Atom\Framework\Support\Contracts\Arrayable as ArrayableContract;
use Eyika\Atom\Framework\Support\Database\Model;
use JsonSerializable;
use PHPUnit\Framework\TestCase;

class SerializedWidget extends Model
{
    public $table = 'atomtest_serialized_widgets';
    public $softdeletes = false;

    protected const fillable = ['id', 'name', 'secret'];
    protected const guarded  = ['secret'];
}

/** A model that declares its columns as real public properties, as apps commonly do. */
class PublicColumnWidget extends Model
{
    public $table = 'atomtest_public_column_widgets';
    public $softdeletes = false;

    public $name;
    public $compiled_js;

    protected const fillable = ['id', 'name', 'compiled_js'];
    protected const guarded  = ['compiled_js'];
}

/**
 * Reported by Claude A (backtestfx): `guarded` promises a column never leaves the application and
 * `toArray()` enforces it, but nothing on the JSON **encode** path called `toArray()`.
 *
 * Two independent defects, and fixing either alone left the hole open:
 *
 *  1. `Model` implemented no serialization contract — not `JsonSerializable`, not
 *     `Contracts\Arrayable` — so `json_encode()` fell back to reading its declared public
 *     properties.
 *  2. `EnumeratesValues` dispatched on `Support\Arrayable` / `Support\Jsonable`, which are
 *     concrete CLASSES, not the interfaces of the same name under `Support\Contracts`. No model
 *     could ever be an instance of a class, so every model fell through `return $value` and was
 *     encoded raw.
 *
 * Note the direct response path was already safe: `BaseResponse::convertObjectsToArray()` calls
 * `toArray()` when the method exists, so `response()->json(['w' => $model])` guarded correctly.
 * The hole was every path that reached `json_encode()` without passing through that method —
 * most importantly a Collection, whose own `toArray()` returned its models untouched.
 */
class ModelSerializationTest extends TestCase
{
    private function widget(): SerializedWidget
    {
        $widget = new SerializedWidget();
        $widget->id = 1;
        $widget->name = 'visible';
        $widget->secret = 'CLASSIFIED';

        return $widget;
    }

    // ---------------------------------------------------------------- contracts

    public function test_a_model_declares_the_serialization_contracts(): void
    {
        $widget = $this->widget();

        $this->assertInstanceOf(JsonSerializable::class, $widget);
        $this->assertInstanceOf(ArrayableContract::class, $widget);
    }

    /** The dispatch that was broken: these must be INTERFACES, or instanceof can never match. */
    public function test_the_collection_dispatch_targets_are_interfaces(): void
    {
        $this->assertTrue(
            (new \ReflectionClass(ArrayableContract::class))->isInterface(),
            'Support\Contracts\Arrayable must be an interface'
        );
        $this->assertTrue(
            (new \ReflectionClass(\Eyika\Atom\Framework\Support\Contracts\Jsonable::class))->isInterface(),
            'Support\Contracts\Jsonable must be an interface'
        );
    }

    // ---------------------------------------------------------------- guarded columns

    public function test_a_bare_model_omits_guarded_columns_when_encoded(): void
    {
        $json = json_encode($this->widget());

        $this->assertStringNotContainsString('CLASSIFIED', $json);
        $this->assertStringContainsString('visible', $json);
    }

    public function test_a_plain_array_of_models_omits_guarded_columns(): void
    {
        $this->assertStringNotContainsString('CLASSIFIED', json_encode([$this->widget()]));
    }

    public function test_a_collection_of_models_omits_guarded_columns(): void
    {
        $this->assertStringNotContainsString('CLASSIFIED', json_encode(new Collection([$this->widget()])));
    }

    /** Collection::toArray() is its own path — it must guard as well as jsonSerialize() does. */
    public function test_collection_to_array_omits_guarded_columns(): void
    {
        $this->assertStringNotContainsString(
            'CLASSIFIED',
            json_encode((new Collection([$this->widget()]))->toArray())
        );
    }

    /** Any iterable of models, which is why JsonSerializable is the right lever. */
    public function test_a_nested_structure_of_models_omits_guarded_columns(): void
    {
        $payload = ['data' => ['items' => [$this->widget()], 'meta' => ['total' => 1]]];

        $this->assertStringNotContainsString('CLASSIFIED', json_encode($payload));
    }

    public function test_a_traversable_of_models_omits_guarded_columns(): void
    {
        $collection = new Collection(new ArrayIterator([$this->widget()]));

        $this->assertStringNotContainsString('CLASSIFIED', json_encode($collection));
    }

    // ---------------------------------------------------------------- internal plumbing

    /**
     * Raw encoding exposed the model's declared public properties, which are framework internals
     * rather than data — `table`, `primaryKey`, `softdeletes`.
     */
    public function test_internal_plumbing_is_not_exposed_in_any_shape(): void
    {
        $shapes = [
            'bare model' => json_encode($this->widget()),
            'array'      => json_encode([$this->widget()]),
            'collection' => json_encode(new Collection([$this->widget()])),
        ];

        foreach ($shapes as $label => $json) {
            foreach (['table', 'primaryKey', 'softdeletes'] as $internal) {
                $this->assertStringNotContainsString(
                    '"' . $internal . '"',
                    $json,
                    "$label leaked the internal property $internal"
                );
            }
        }
    }

    // ---------------------------------------------------------------- declared-property columns

    /**
     * The case that actually exposes DATA rather than plumbing, and the one worth guarding.
     *
     * Columns assigned through `__set` land in `dynamicProperties`, which is not public, so raw
     * encoding never reached them. A model that DECLARES a column as a public property is a
     * different matter: that property is public, and raw encoding emitted it with the guard
     * bypassed. Apps do declare columns this way, so this is the shape in which a proprietary
     * column can genuinely leave the application.
     */
    public function test_a_guarded_column_declared_as_a_public_property_is_still_omitted(): void
    {
        $widget = new PublicColumnWidget();
        $widget->id = 1;
        $widget->name = 'visible';
        $widget->compiled_js = 'PROPRIETARY';

        foreach ([
            'bare model' => json_encode($widget),
            'array'      => json_encode([$widget]),
            'collection' => json_encode(new Collection([$widget])),
        ] as $label => $json) {
            $this->assertStringNotContainsString(
                'PROPRIETARY',
                $json,
                "$label leaked a guarded column declared as a public property"
            );
        }
    }

    // ---------------------------------------------------------------- the server-side escape hatch

    /** Server-side callers legitimately load guarded columns; that must keep working. */
    public function test_to_array_false_still_returns_guarded_columns(): void
    {
        $this->assertArrayHasKey('secret', $this->widget()->toArray(false));
    }

    public function test_to_array_guards_by_default(): void
    {
        $this->assertArrayNotHasKey('secret', $this->widget()->toArray());
    }

    // ---------------------------------------------------------------- no collateral damage

    public function test_a_collection_of_plain_arrays_encodes_unchanged(): void
    {
        $collection = new Collection([['a' => 1], ['b' => 2]]);

        $this->assertSame('[{"a":1},{"b":2}]', json_encode($collection));
    }

    public function test_a_collection_of_scalars_encodes_unchanged(): void
    {
        $this->assertSame('[1,"two",3.5,true,null]', json_encode(new Collection([1, 'two', 3.5, true, null])));
    }

    /** The concrete Support\Arrayable / Support\Jsonable must still be matched after repointing. */
    public function test_the_concrete_support_classes_still_satisfy_the_contracts(): void
    {
        $this->assertInstanceOf(
            ArrayableContract::class,
            new \Eyika\Atom\Framework\Support\Arrayable(['a' => 1])
        );
        $this->assertInstanceOf(
            \Eyika\Atom\Framework\Support\Contracts\Jsonable::class,
            new \Eyika\Atom\Framework\Support\Jsonable(['a' => 1])
        );
    }
}
