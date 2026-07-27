<?php

namespace Eyika\Atom\Framework\Tests\Unit\Foundation;

use Eyika\Atom\Framework\Foundation\Application;
use Eyika\Atom\Framework\Support\Facade\Facade;
use PHPUnit\Framework\TestCase;
use stdClass;

interface ContainerThing
{
}

class ContainerScalarDefault
{
    public function __construct(public string $name = 'default')
    {
    }
}

class ContainerNeedsClass
{
    public function __construct(public stdClass $dep)
    {
    }
}

class ContainerNeedsThing
{
    public function __construct(public ?ContainerThing $thing = null)
    {
    }
}

/**
 * Covers BUG-37 (make() cached every resolution → bind()/auto-resolved became
 * de-facto singletons), BUG-38 (autowiring crashed on scalar/union/nullable params),
 * BUG-39 (singleton resolver got no container arg), BUG-40 ($aliases/$resolved
 * undeclared → isAlias()/offsetUnset() errored).
 */
class ContainerTest extends TestCase
{
    private Application $c;

    protected function setUp(): void
    {
        parent::setUp();
        $this->c = new Application($GLOBALS['base_path'], true);
        Facade::setFacadeApplication($this->c);
        Facade::clearResolvedInstances();
    }

    public function test_bind_is_transient(): void
    {
        $this->c->bind('trans', fn() => new stdClass());

        $this->assertNotSame($this->c->make('trans'), $this->c->make('trans'));
    }

    public function test_singleton_is_shared(): void
    {
        $this->c->singleton('single', fn() => new stdClass());

        $this->assertSame($this->c->make('single'), $this->c->make('single'));
    }

    public function test_instance_returns_the_exact_object(): void
    {
        $obj = new stdClass();
        $this->c->instance('inst', $obj);

        $this->assertSame($obj, $this->c->make('inst'));
    }

    public function test_singleton_resolver_receives_the_container(): void
    {
        $this->c->singleton('ctx', fn($app) => $app);

        $this->assertSame($this->c, $this->c->make('ctx'));
    }

    public function test_autowires_scalar_default_without_crashing(): void
    {
        $obj = $this->c->make(ContainerScalarDefault::class);

        $this->assertInstanceOf(ContainerScalarDefault::class, $obj);
        $this->assertSame('default', $obj->name);
    }

    public function test_autowires_resolvable_class_dependency(): void
    {
        $obj = $this->c->make(ContainerNeedsClass::class);

        $this->assertInstanceOf(stdClass::class, $obj->dep);
    }

    public function test_autowires_unresolvable_nullable_dependency_to_null(): void
    {
        $obj = $this->c->make(ContainerNeedsThing::class);

        $this->assertNull($obj->thing);
    }

    public function test_is_alias_and_offset_unset_do_not_error(): void
    {
        $this->assertFalse($this->c->isAlias('nope'));

        $this->c->bind('gone', fn() => 1);
        $this->c->offsetUnset('gone');
        $this->assertFalse($this->c->has('gone'));
    }
}
