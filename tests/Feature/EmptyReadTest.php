<?php

namespace Eyika\Atom\Framework\Tests\Feature;

use Eyika\Atom\Framework\Support\Collections\Collection;
use Eyika\Atom\Framework\Support\Database\Model;

class EmptyReadWidget extends Model
{
    public $table = 'atomtest_empty_widgets';
    public $softdeletes = false;

    protected const fillable = ['id', 'name'];
}

/**
 * Reported by Claude C (vendra): `get()`/`all()` returned false rather than an empty Collection
 * when nothing matched, because `_all()` bailed on a falsy `fetch()` result — and `[]` (matched
 * nothing) is just as falsy as `false` (cursor failed). So the documented "multi-result reads
 * return a Collection" only held for a NON-empty result, and `assertCount(0, …->get())` fatalled
 * with a TypeError. Connection::fetch() already distinguishes the two; _all() now does too.
 */
class EmptyReadTest extends DatabaseTestCase
{
    protected function createSchema(): void
    {
        $this->raw('DROP TABLE IF EXISTS atomtest_empty_widgets');
        $this->raw('CREATE TABLE atomtest_empty_widgets (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(50), created_at DATETIME NULL, updated_at DATETIME NULL)');
        $this->raw("INSERT INTO atomtest_empty_widgets (id, name) VALUES (1, 'present')");
    }

    protected function dropSchema(): void
    {
        $this->raw('DROP TABLE IF EXISTS atomtest_empty_widgets');
    }

    public function test_empty_read_returns_an_empty_collection(): void
    {
        $rows = (new EmptyReadWidget())->where('name', 'no-such-row')->get();

        $this->assertInstanceOf(Collection::class, $rows);
        $this->assertCount(0, $rows);
    }

    public function test_empty_read_is_safely_iterable(): void
    {
        $seen = 0;
        foreach ((new EmptyReadWidget())->where('name', 'no-such-row')->get() as $_) {
            $seen++;
        }

        $this->assertSame(0, $seen);
    }

    public function test_non_empty_read_still_returns_a_populated_collection(): void
    {
        $rows = (new EmptyReadWidget())->where('name', 'present')->get();

        $this->assertInstanceOf(Collection::class, $rows);
        $this->assertCount(1, $rows);
        $this->assertSame('present', $rows[0]->name);
    }

    public function test_all_on_an_empty_table_returns_an_empty_collection(): void
    {
        $this->raw('DELETE FROM atomtest_empty_widgets');

        $rows = (new EmptyReadWidget())->all();

        $this->assertInstanceOf(Collection::class, $rows);
        $this->assertCount(0, $rows);
    }
}
