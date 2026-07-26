<?php

namespace Eyika\Atom\Framework\Tests\Feature;

use Eyika\Atom\Framework\Support\Database\DB;
use Eyika\Atom\Framework\Support\Database\Model;

class EventWidget extends Model
{
    public $table = 'atomtest_events';
    public $softdeletes = false;
    // NOTE: the framework reads the `fillable` CONST (not a $fillable property) —
    // declaring it as a property leaves `name` non-fillable, so writes drop it.
    const fillable = ['id', 'name'];
}

/**
 * Covers BUG-20: model lifecycle events never fired (boot/booting/booted were empty
 * stubs). Now boot() dispatches registered listeners, a "before" listener returning
 * false aborts the write, and the registration API is Laravel-style.
 */
class ModelEventTest extends DatabaseTestCase
{
    protected function createSchema(): void
    {
        $this->raw('DROP TABLE IF EXISTS atomtest_events');
        $this->raw('CREATE TABLE atomtest_events (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(50), created_at DATETIME NULL, updated_at DATETIME NULL)');
    }

    protected function dropSchema(): void
    {
        $this->raw('DROP TABLE IF EXISTS atomtest_events');
        EventWidget::flushEventListeners();
    }

    public function test_creating_listener_fires_with_the_model(): void
    {
        $seen = [];
        EventWidget::creating(function ($model) use (&$seen) {
            $seen[] = $model->name;
        });

        (new EventWidget())->create(['name' => 'ada']);

        $this->assertSame(['ada'], $seen);
    }

    public function test_creating_listener_returning_false_aborts_the_insert(): void
    {
        EventWidget::creating(fn ($model) => false);

        $result = (new EventWidget())->create(['name' => 'blocked']);

        $this->assertFalse($result);
        $this->assertFalse(DB::table('atomtest_events')->where('name', 'blocked')->first());
    }

    public function test_created_and_retrieved_listeners_fire(): void
    {
        $fired = [];
        EventWidget::created(function () use (&$fired) {
            $fired[] = 'created';
        });
        EventWidget::retrieved(function () use (&$fired) {
            $fired[] = 'retrieved';
        });

        $widget = (new EventWidget())->create(['name' => 'bob']);
        $this->assertContains('created', $fired);

        (new EventWidget())->find($widget->id);
        $this->assertContains('retrieved', $fired);
    }
}
