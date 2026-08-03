<?php

namespace Eyika\Atom\Framework\Tests\Feature;

use Eyika\Atom\Framework\Support\Database\Model;

class GuardedWidget extends Model
{
    public $table = 'atomtest_guarded_widgets';
    public $softdeletes = false;

    protected const fillable = ['id', 'name', 'secret', 'created_at'];
    protected const guarded  = ['secret'];
}

/**
 * Reported by Claude C (vendra): a protected read dropped guarded columns from the SELECT, so the
 * model's own property came back null — `created_at` off a plain `->get()` was null even though
 * the row had it, and a service reading that timestamp silently computed the wrong answer.
 *
 * `guarded` is an OUTPUT filter (its own docblock says "exposed outside the application"), so it
 * no longer restricts what is loaded. Exposure is still enforced by toArray(), which guards by
 * default and is what the JSON response path calls — that is asserted here too, because dropping
 * the read-side filter must not weaken it.
 */
class GuardedHydrationTest extends DatabaseTestCase
{
    protected function createSchema(): void
    {
        $this->raw('DROP TABLE IF EXISTS atomtest_guarded_widgets');
        $this->raw('CREATE TABLE atomtest_guarded_widgets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(50) NULL,
            secret VARCHAR(50) NULL,
            created_at DATETIME NULL,
            updated_at DATETIME NULL
        )');
        $this->raw("INSERT INTO atomtest_guarded_widgets (id, name, secret, created_at)
                    VALUES (1, 'visible', 'classified', '2026-01-02 03:04:05')");
    }

    protected function dropSchema(): void
    {
        $this->raw('DROP TABLE IF EXISTS atomtest_guarded_widgets');
    }

    public function test_a_protected_get_still_hydrates_timestamps(): void
    {
        $rows = (new GuardedWidget())->where('id', 1)->get();   // $is_protected defaults to true
        $row  = (is_array($rows) ? $rows : $rows->all())[0];

        $this->assertSame('2026-01-02 03:04:05', $row->created_at);
    }

    public function test_a_protected_find_hydrates_guarded_columns(): void
    {
        $row = (new GuardedWidget())->find(1);

        $this->assertSame('classified', $row->secret, 'the app must be able to read its own data');
    }

    /** Dropping the read-side filter must not weaken what actually leaves the application. */
    public function test_guarded_columns_are_still_withheld_from_output(): void
    {
        $row = (new GuardedWidget())->find(1);

        $output = $row->toArray();

        $this->assertArrayNotHasKey('secret', $output, 'toArray() must still guard on output');
        $this->assertArrayHasKey('name', $output);
        $this->assertArrayHasKey('created_at', $output);
    }

    public function test_unguarded_output_still_available_explicitly(): void
    {
        $row = (new GuardedWidget())->find(1);

        $this->assertArrayHasKey('secret', $row->toArray(false));
    }
}
