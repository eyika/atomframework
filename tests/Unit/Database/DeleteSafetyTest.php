<?php

namespace Eyika\Atom\Framework\Tests\Unit\Database;

use Eyika\Atom\Framework\Support\Database\DB;
use Eyika\Atom\Framework\Support\Database\Model;
use Exception;
use PHPUnit\Framework\TestCase;

/**
 * Covers BUG-35: a delete() with no id and no where() filter must be REFUSED — it
 * would otherwise emit `DELETE FROM table` and wipe the whole table (or, with a
 * null id, silently match nothing). The guard fires before any DB access.
 */
class DeleteSafetyTest extends TestCase
{
    public function test_model_delete_without_id_or_filter_is_refused(): void
    {
        $model = new class extends Model {
            public $table = 'stub';
        };

        $this->expectException(Exception::class);
        $model->_delete();
    }

    public function test_static_db_delete_without_id_or_filter_is_refused(): void
    {
        $this->expectException(Exception::class);
        (new DB())->delete();
    }
}
