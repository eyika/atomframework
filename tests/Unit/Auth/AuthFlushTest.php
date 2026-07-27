<?php

namespace Eyika\Atom\Framework\Tests\Unit\Auth;

use Eyika\Atom\Framework\Support\Auth\Auth;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * Covers WRK-03: Auth holds the resolved user / jwt / sid / impersonation in STATIC
 * state. Under a persistent worker that leaks into the next request (a cross-user
 * identity leak). Auth::flush() must clear all of it between requests.
 */
class AuthFlushTest extends TestCase
{
    private function prop(string $name): ReflectionProperty
    {
        $p = new ReflectionProperty(Auth::class, $name);
        $p->setAccessible(true);
        return $p;
    }

    public function test_flush_clears_all_per_request_identity_state(): void
    {
        // Simulate an authenticated request's residual static state.
        $this->prop('user')->setValue(null, (object) ['id' => 42]);
        $this->prop('guardName')->setValue(null, 'api');
        $this->prop('jwt')->setValue(null, 'jwt-token');
        $this->prop('sid')->setValue(null, 'session-id');
        $this->prop('impersonatorId')->setValue(null, 7);
        $this->prop('isImpersonating')->setValue(null, true);
        $this->prop('guards')->setValue(null, ['api' => (object) []]);

        Auth::flush();

        $this->assertNull($this->prop('user')->getValue());
        $this->assertSame('', Auth::getJwt());
        $this->assertSame('', Auth::getSid());
        $this->assertSame([], $this->prop('guards')->getValue());
        $this->assertNull($this->prop('impersonatorId')->getValue());
        $this->assertFalse($this->prop('isImpersonating')->getValue());

        // Cleared to null so init() re-derives the guard from the next request
        // (isset(null) === false).
        $this->assertNull($this->prop('guardName')->getValue());
    }
}
