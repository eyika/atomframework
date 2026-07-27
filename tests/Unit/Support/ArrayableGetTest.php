<?php

namespace Eyika\Atom\Framework\Tests\Unit\Support;

use Eyika\Atom\Framework\Support\Arrayable;
use PHPUnit\Framework\TestCase;

/**
 * Regression: Arrayable::get() ignored its $key and passed $default as the key to
 * Arr::get(), so it always returned the whole backing array.
 */
class ArrayableGetTest extends TestCase
{
    public function test_get_returns_value_for_key(): void
    {
        $a = new Arrayable(['name' => 'ada', 'role' => 'admin']);

        $this->assertSame('ada', $a->get('name'));
        $this->assertSame('admin', $a->get('role'));
    }

    public function test_get_returns_default_for_missing_key(): void
    {
        $a = new Arrayable(['name' => 'ada']);

        $this->assertNull($a->get('missing'));
        $this->assertSame('fallback', $a->get('missing', 'fallback'));
    }

    public function test_get_supports_dot_notation(): void
    {
        $a = new Arrayable(['user' => ['email' => 'a@b.com']]);

        $this->assertSame('a@b.com', $a->get('user.email'));
    }
}
