<?php

namespace Eyika\Atom\Framework\Tests\Feature;

use Eyika\Atom\Framework\Exceptions\Db\ModelNotFoundException;
use Eyika\Atom\Framework\Http\Middlewares\SubstituteBindings;
use Eyika\Atom\Framework\Http\Request;
use Eyika\Atom\Framework\Support\Database\Model;

class BindingWidget extends Model
{
    public $table = 'atomtest_binding_widgets';
    public $softdeletes = false;

    protected const fillable = ['id', 'slug', 'name'];
}

/** Binds by slug rather than id. */
class BindingSluggable extends Model
{
    public $table = 'atomtest_binding_widgets';
    public $softdeletes = false;

    protected const fillable = ['id', 'slug', 'name'];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}

/**
 * The real middleware discovers models by scanning app/Models; these fixtures live in the test
 * namespace, so the lookup seam is overridden rather than planting files on disk. Everything
 * under test — the binding decision, the route key, the not-found path — is untouched.
 */
class BindingMiddlewareStub extends SubstituteBindings
{
    protected function modelClassForKey(string $key): ?string
    {
        return [
            'bindingwidget'    => BindingWidget::class,
            'bindingsluggable' => BindingSluggable::class,
        ][$key] ?? null;
    }
}

/**
 * Covers BUG-18: SubstituteBindings only bound values passing is_numeric(), so a slug or UUID
 * route parameter was never resolved — the controller received the raw URL segment where it
 * expected a model. Binding is now attempted for every parameter that names a model, against
 * the model's route key (the primary key unless getRouteKeyName() says otherwise).
 */
class RouteBindingTest extends DatabaseTestCase
{
    protected function createSchema(): void
    {
        $this->raw('DROP TABLE IF EXISTS atomtest_binding_widgets');
        $this->raw('CREATE TABLE atomtest_binding_widgets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            slug VARCHAR(60) NULL,
            name VARCHAR(50) NULL,
            created_at DATETIME NULL,
            updated_at DATETIME NULL
        )');
        $this->raw("INSERT INTO atomtest_binding_widgets (id, slug, name) VALUES (1, 'first-widget', 'First')");
    }

    protected function dropSchema(): void
    {
        $this->raw('DROP TABLE IF EXISTS atomtest_binding_widgets');
    }

    /** Drive the middleware with a given set of route params. */
    private function substitute(array $routeParams): array
    {
        $request = new Request();
        $request->route_params = $routeParams;

        (new BindingMiddlewareStub())->handle($request, function (Request $r) {
            return new \Eyika\Atom\Framework\Http\BaseResponse();
        });

        return $request->route_params;
    }

    public function test_route_key_name_defaults_to_the_primary_key(): void
    {
        $this->assertSame('id', (new BindingWidget())->getRouteKeyName());
    }

    public function test_a_model_can_bind_by_a_custom_route_key(): void
    {
        $this->assertSame('slug', (new BindingSluggable())->getRouteKeyName());
    }

    /**
     * The regression itself: a non-numeric segment used to fall straight through the
     * is_numeric() gate and arrive at the controller as a string.
     */
    public function test_non_numeric_values_are_no_longer_skipped_outright(): void
    {
        $params = $this->substitute(['bindingsluggable' => 'first-widget']);

        $this->assertNotSame(
            'first-widget',
            $params['bindingsluggable'],
            'a slug naming a model must not pass through as a raw string'
        );
        $this->assertInstanceOf(BindingSluggable::class, $params['bindingsluggable']);
        $this->assertSame('First', $params['bindingsluggable']->name);
    }

    public function test_numeric_binding_by_primary_key_still_works(): void
    {
        $params = $this->substitute(['bindingwidget' => 1]);

        $this->assertInstanceOf(BindingWidget::class, $params['bindingwidget']);
        $this->assertEquals(1, $params['bindingwidget']->id);
    }

    public function test_a_parameter_naming_no_model_is_left_alone(): void
    {
        $params = $this->substitute(['format' => 'csv', 'page' => 3]);

        $this->assertSame('csv', $params['format']);
        $this->assertSame(3, $params['page']);
    }

    /**
     * BUG-19: find()/first() return null on a miss (BUG-23), and resolveModel() also used null
     * for "not a model parameter" — so handle() skipped a missing row instead of raising, and
     * the controller received the raw URL segment. Both finder paths are covered.
     */
    public function test_a_missing_row_raises_model_not_found(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->substitute(['bindingsluggable' => 'no-such-slug']);
    }

    public function test_a_missing_numeric_id_raises_model_not_found(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->substitute(['bindingwidget' => 999999]);
    }
}
