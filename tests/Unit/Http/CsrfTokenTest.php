<?php

namespace Eyika\Atom\Framework\Tests\Unit\Http;

use Eyika\Atom\Framework\Http\Csrf;
use PHPUnit\Framework\TestCase;

/**
 * Covers SEC-06: token generation must be random and never fall back to a
 * predictable constant.
 */
class CsrfTokenTest extends TestCase
{
    public function test_regenerate_token_is_long_random_hex(): void
    {
        $token = Csrf::regenerateToken();

        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);
        $this->assertNotSame('123456789012345678901234567890', $token);
    }

    public function test_tokens_are_unique(): void
    {
        $this->assertNotSame(Csrf::regenerateToken(), Csrf::regenerateToken());
    }
}
