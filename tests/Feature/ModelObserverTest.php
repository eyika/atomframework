<?php

namespace Eyika\Atom\Framework\Tests\Feature;

use Eyika\Atom\Framework\Support\Database\DB;
use Eyika\Atom\Framework\Support\Database\Model;

class ObservedWidget extends Model
{
    public $table = 'atomtest_observed';
    public $softdeletes = false;
    const fillable = ['id', 'name'];
}

class WidgetObserver
{
    public static array $events = [];

    public function creating($model): void
    {
        self::$events[] = 'creating:' . $model->name;
    }

    public function created($model): void
    {
        self::$events[] = 'created:' . $model->name;
    }

    public function deleting($model): void
    {
        self::$events[] = 'deleting';
    }
}

class BlockingObserver
{
    public function creating($model): bool
    {
        return false; // abort the write
    }
}

/**
 * Covers Model::observe(): an observer's lifecycle methods (creating/created/…) are
 * registered as listeners for that model's events, receive the model, and a "before"
 * method returning false aborts the write.
 */
class ModelObserverTest extends DatabaseTestCase
{
    protected function createSchema(): void
    {
        $this->raw('DROP TABLE IF EXISTS atomtest_observed');
        $this->raw('CREATE TABLE atomtest_observed (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(50), created_at DATETIME NULL, updated_at DATETIME NULL)');
        WidgetObserver::$events = [];
    }

    protected function dropSchema(): void
    {
        $this->raw('DROP TABLE IF EXISTS atomtest_observed');
        ObservedWidget::flushEventListeners();
        WidgetObserver::$events = [];
    }

    public function test_observer_lifecycle_methods_fire_with_the_model(): void
    {
        ObservedWidget::observe(WidgetObserver::class);

        $widget = (new ObservedWidget())->create(['name' => 'ada']);
        $this->assertNotFalse($widget);

        $this->assertContains('creating:ada', WidgetObserver::$events);
        $this->assertContains('created:ada', WidgetObserver::$events);
    }

    public function test_before_observer_returning_false_aborts_the_write(): void
    {
        ObservedWidget::observe(BlockingObserver::class);

        $result = (new ObservedWidget())->create(['name' => 'blocked']);

        $this->assertFalse($result);
        $this->assertFalse(DB::table('atomtest_observed')->where('name', 'blocked')->first());
    }
}
