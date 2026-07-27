<?php

namespace Eyika\Atom\Framework\Tests\Unit\Event;

use Eyika\Atom\Framework\Foundation\Event\Dispatcher;
use PHPUnit\Framework\TestCase;

class SampleEvent
{
    public function __construct(public string $name)
    {
    }
}

class SampleListener
{
    public static array $seen = [];

    public function handle($event): void
    {
        self::$seen[] = $event->name ?? $event;
    }

    public function onFoo($payload): void
    {
        self::$seen[] = 'foo:' . $payload;
    }
}

/**
 * Covers the upgraded event Dispatcher: object + string events, payloads, wildcards,
 * until()/false halting, class listeners (handle / Class@method), hasListeners/forget.
 */
class DispatcherTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        SampleListener::$seen = [];
    }

    public function test_object_event_dispatch(): void
    {
        $d = new Dispatcher();
        $got = null;
        $d->listen(SampleEvent::class, function (SampleEvent $e) use (&$got) {
            $got = $e->name;
        });

        $d->dispatch(new SampleEvent('ada'));

        $this->assertSame('ada', $got);
    }

    public function test_string_event_with_payload(): void
    {
        $d = new Dispatcher();
        $got = [];
        $d->listen('user.registered', function ($user, $ip) use (&$got) {
            $got = [$user, $ip];
        });

        $d->dispatch('user.registered', ['bob', '1.2.3.4']);

        $this->assertSame(['bob', '1.2.3.4'], $got);
    }

    public function test_wildcard_listener(): void
    {
        $d = new Dispatcher();
        $hits = [];
        $d->listen('model.*', function ($data) use (&$hits) {
            $hits[] = $data;
        });

        $d->dispatch('model.created', ['A']);
        $d->dispatch('model.deleted', ['B']);
        $d->dispatch('other.event', ['C']); // no match

        $this->assertSame(['A', 'B'], $hits);
    }

    public function test_until_returns_first_non_null_and_halts(): void
    {
        $d = new Dispatcher();
        $ranSecond = false;
        $d->listen('gate', fn () => null);
        $d->listen('gate', fn () => 'blocked');
        $d->listen('gate', function () use (&$ranSecond) {
            $ranSecond = true;
        });

        $this->assertSame('blocked', $d->until('gate'));
        $this->assertFalse($ranSecond, 'propagation should stop at the first non-null response');
    }

    public function test_returning_false_halts_propagation(): void
    {
        $d = new Dispatcher();
        $ran = false;
        $d->listen('chain', fn () => false);
        $d->listen('chain', function () use (&$ran) {
            $ran = true;
        });

        $d->dispatch('chain');

        $this->assertFalse($ran);
    }

    public function test_class_and_class_at_method_listeners(): void
    {
        $d = new Dispatcher();
        $d->listen(SampleEvent::class, SampleListener::class);          // ->handle()
        $d->listen('foo', SampleListener::class . '@onFoo');            // Class@method

        $d->dispatch(new SampleEvent('x'));
        $d->dispatch('foo', ['bar']);

        $this->assertSame(['x', 'foo:bar'], SampleListener::$seen);
    }

    public function test_has_listeners_and_forget(): void
    {
        $d = new Dispatcher();
        $d->listen('a', fn () => null);
        $d->listen('ns.*', fn () => null);

        $this->assertTrue($d->hasListeners('a'));
        $this->assertTrue($d->hasListeners('ns.thing'));
        $this->assertFalse($d->hasListeners('b'));

        $d->forget('a');
        $this->assertFalse($d->hasListeners('a'));
    }
}
