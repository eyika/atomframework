<?php

namespace Eyika\Atom\Framework\Tests\Feature;

use Eyika\Atom\Framework\Support\Database\DB;
use Eyika\Atom\Framework\Support\Database\Model;

class SaveWidget extends Model
{
    public $table = 'atomtest_widgets';
    public $softdeletes = false;
    protected $fillable = ['id', 'name'];
}

/**
 * Covers BUG-29: _save()'s update branch indexed __update()'s FLAT return array as
 * $model[0][...] (and used the property value as the key), nulling the primary key
 * and updated_at after every save. The row is updated but the in-memory model is
 * corrupted.
 */
class SaveTest extends DatabaseTestCase
{
    protected function createSchema(): void
    {
        $this->raw('DROP TABLE IF EXISTS atomtest_widgets');
        $this->raw('CREATE TABLE atomtest_widgets (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(50), created_at DATETIME NULL, updated_at DATETIME NULL)');
        $this->raw("INSERT INTO atomtest_widgets (id, name) VALUES (1, 'old')");
    }

    protected function dropSchema(): void
    {
        $this->raw('DROP TABLE IF EXISTS atomtest_widgets');
    }

    public function test_save_update_preserves_primary_key(): void
    {
        $widget = (new SaveWidget())->find(1);
        $this->assertNotFalse($widget);
        $this->assertEquals(1, $widget->id);

        // BUG-29: save()'s update branch nulled the PK (and updated_at) here because
        // it indexed __update()'s flat array as $model[0][...].
        $widget->save();

        $this->assertEquals(1, $widget->id);
        // The row still exists with its PK intact (not orphaned by a corrupted model).
        $this->assertNotFalse(DB::table('atomtest_widgets')->where('id', 1)->first());
    }
}
