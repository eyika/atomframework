<?php

namespace Eyika\Atom\Framework\Tests\Unit\Http;

use Eyika\Atom\Framework\Http\Middlewares\SubstituteBindings;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * Exposes the protected key->class resolver so the memoization can be asserted.
 */
class ExposedSubstituteBindings extends SubstituteBindings
{
    public function resolveKey(string $key): ?string
    {
        return $this->modelClassForKey($key);
    }
}

/**
 * Covers PERF-01/BUG-17: the route-param => model-class map is memoized on a static
 * (built once per process) instead of walking app/Models on every parameter of every
 * request. The lookup is keyed by the lowercased short class name.
 */
class SubstituteBindingsModelMapTest extends TestCase
{
    private function mapProp(): ReflectionProperty
    {
        $p = new ReflectionProperty(SubstituteBindings::class, 'modelMap');
        $p->setAccessible(true);
        return $p;
    }

    protected function setUp(): void
    {
        parent::setUp();
        SubstituteBindings::flushModelMap();
    }

    protected function tearDown(): void
    {
        SubstituteBindings::flushModelMap();
        parent::tearDown();
    }

    public function test_lookup_reads_from_the_memoized_map(): void
    {
        // Seed the cache so no filesystem walk is needed.
        $this->mapProp()->setValue(null, [
            'user' => 'App\\Models\\User',
            'strategy' => 'App\\Models\\Strategy',
        ]);

        $mw = new ExposedSubstituteBindings();
        $this->assertSame('App\\Models\\User', $mw->resolveKey('user'));
        $this->assertSame('App\\Models\\Strategy', $mw->resolveKey('strategy'));
        $this->assertNull($mw->resolveKey('nonexistent'));
    }

    public function test_flush_resets_the_cache(): void
    {
        $this->mapProp()->setValue(null, ['user' => 'App\\Models\\User']);
        $this->assertIsArray($this->mapProp()->getValue());

        SubstituteBindings::flushModelMap();

        $this->assertNull($this->mapProp()->getValue());
    }
}
