<?php

namespace Eyika\Atom\Framework\Tests\Unit\Support;

use Eyika\Atom\Framework\Support\Config;
use PHPUnit\Framework\TestCase;

/**
 * `Config::$config` is process-wide static state, so a `set()` in one test leaks into every test
 * that runs after it — an app team hit this and had to restore by hand in `tearDown()`.
 *
 * `clearCache()` is not the answer: it wipes the whole array AND deletes the compiled cache
 * artifact, which is a far bigger hammer than "undo my override". `snapshot()`/`restore()` are the
 * narrow pair, and the framework's testing base classes now call them automatically.
 *
 * The two tests below are deliberately order-dependent: the first mutates config, the second
 * asserts the mutation did not survive. PHPUnit runs them in declaration order.
 */
class ConfigIsolationTest extends TestCase
{
    private array $snapshot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->snapshot = Config::snapshot();
    }

    protected function tearDown(): void
    {
        Config::restore($this->snapshot);
        parent::tearDown();
    }

    public function test_a_config_override_applies_within_the_test(): void
    {
        Config::set('app.kill_switch_probe', 'on');

        $this->assertSame('on', Config::get('app.kill_switch_probe'));
    }

    /** Runs after the test above; the override must not have survived its tearDown. */
    public function test_the_override_did_not_leak_into_the_next_test(): void
    {
        $this->assertNull(
            Config::get('app.kill_switch_probe'),
            'a config override leaked out of the test that set it'
        );
    }

    public function test_restore_puts_back_a_value_that_was_overwritten(): void
    {
        $original = Config::get('app.name');

        Config::set('app.name', 'Overridden');
        $this->assertSame('Overridden', Config::get('app.name'));

        Config::restore($this->snapshot);
        $this->assertSame($original, Config::get('app.name'));
    }

    /** A snapshot is a copy, not a live reference — otherwise restoring would be a no-op. */
    public function test_a_snapshot_is_not_affected_by_later_writes(): void
    {
        $before = Config::snapshot();

        Config::set('app.name', 'Changed After Snapshot');

        $this->assertNotSame('Changed After Snapshot', $before['app']['name'] ?? null);
    }
}
