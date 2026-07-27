<?php

namespace Eyika\Atom\Framework\Tests\Unit\Support;

use Eyika\Atom\Framework\Support\Config;
use PHPUnit\Framework\TestCase;

/**
 * Covers BUG-48: Config::clearCache() threw NotImplemented. Now it drops the
 * in-memory config + singleton (so the next access reloads from disk) and
 * best-effort clears the persistent cache.
 */
class ConfigTest extends TestCase
{
    protected function tearDown(): void
    {
        Config::clearCache();
        parent::tearDown();
    }

    public function test_clear_cache_reloads_config_from_disk(): void
    {
        Config::set('app.name', 'MUTATED');
        $this->assertSame('MUTATED', config('app.name'));

        Config::clearCache();

        // Reloaded from the fixture config (config/app.php).
        $this->assertSame('AtomFixture', config('app.name'));
    }
}
