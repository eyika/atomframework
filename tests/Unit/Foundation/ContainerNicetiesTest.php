<?php

namespace Eyika\Atom\Framework\Tests\Unit\Foundation;

use Eyika\Atom\Framework\Foundation\Concerns\ServiceContainer;
use PHPUnit\Framework\TestCase;

class NicetiesContainer
{
    use ServiceContainer;
}

class ContainerDep
{
    public int $val = 7;
}

class ContainerGreeter
{
    public function greet(ContainerDep $dep, string $who): string
    {
        return "$who:" . $dep->val;
    }
}

/**
 * Covers PKG-07: container niceties — alias resolution, extend() decoration (of both
 * instances and bindings), tag()/tagged(), and call() dependency injection with
 * by-name overrides.
 */
class ContainerNicetiesTest extends TestCase
{
    public function test_alias_resolves_to_the_underlying_binding(): void
    {
        $c = new NicetiesContainer();
        $obj = new \stdClass();
        $c->instance('original', $obj);
        $c->alias('original', 'nick');

        $this->assertSame($obj, $c->make('nick'));
    }

    public function test_extend_decorates_an_instance(): void
    {
        $c = new NicetiesContainer();
        $c->instance('svc', (object) ['v' => 1]);
        $c->extend('svc', fn ($service, $app) => (object) ['v' => $service->v + 10]);

        $this->assertSame(11, $c->make('svc')->v);
    }

    public function test_extend_decorates_a_binding(): void
    {
        $c = new NicetiesContainer();
        $c->bind('svc', fn () => (object) ['v' => 1]);
        $c->extend('svc', fn ($service) => (object) ['v' => $service->v + 5]);

        $this->assertSame(6, $c->make('svc')->v);
    }

    public function test_tag_and_tagged_resolve_all(): void
    {
        $c = new NicetiesContainer();
        $c->instance('a', 'A');
        $c->instance('b', 'B');
        $c->tag(['a', 'b'], 'letters');

        $this->assertSame(['A', 'B'], $c->tagged('letters'));
    }

    public function test_call_injects_class_deps_and_honours_overrides(): void
    {
        $c = new NicetiesContainer();
        $c->instance(ContainerDep::class, new ContainerDep());

        // ContainerDep autowired from the container; $n overridden by name.
        $result = $c->call(fn (ContainerDep $d, int $n = 5) => $d->val + $n, ['n' => 10]);

        $this->assertSame(17, $result);
    }

    public function test_call_supports_class_at_method_syntax(): void
    {
        $c = new NicetiesContainer();
        $c->instance(ContainerDep::class, new ContainerDep());

        $result = $c->call(ContainerGreeter::class . '@greet', ['who' => 'ada']);

        $this->assertSame('ada:7', $result);
    }

    public function test_scoped_memoizes_within_a_request(): void
    {
        $c = new NicetiesContainer();
        $count = 0;
        $c->scoped('svc', function () use (&$count) {
            $count++;
            return (object) ['n' => $count];
        });

        $a = $c->make('svc');
        $b = $c->make('svc');

        $this->assertSame($a, $b);   // resolved once
        $this->assertSame(1, $count);
    }

    public function test_forget_scoped_instances_reresolves_next_request(): void
    {
        $c = new NicetiesContainer();
        $count = 0;
        $c->scoped('svc', function () use (&$count) {
            $count++;
            return (object) ['n' => $count];
        });

        $first = $c->make('svc');
        $c->forgetScopedInstances();     // simulate end of request
        $second = $c->make('svc');

        $this->assertNotSame($first, $second);
        $this->assertSame(2, $count);
    }

    public function test_flush_clears_all_bindings(): void
    {
        $c = new NicetiesContainer();
        $c->instance('x', 'X');
        $c->bind('y', fn () => 'Y');

        $c->flush();

        $this->assertFalse($c->bound('x'));
        $this->assertFalse($c->bound('y'));
    }
}
