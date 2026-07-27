<?php

namespace Eyika\Atom\Framework\Tests\Feature;

use PHPUnit\Framework\Assert;

/**
 * Captured result of a fabricated request dispatched through the routing pipeline.
 * `$body` is whatever the response echoed (via Response::send()); `$status` is the
 * boolean dispatch returned. Header/status-code assertions are covered by unit
 * tests of Response/Cookie until the response layer is made injectable (WRK-02).
 */
class TestResponse
{
    public function __construct(public string $body, public mixed $status)
    {
    }

    public function assertBodyContains(string $needle): self
    {
        Assert::assertStringContainsString($needle, $this->body);
        return $this;
    }

    public function assertBodyIs(string $expected): self
    {
        Assert::assertSame($expected, $this->body);
        return $this;
    }

    public function assertJsonFragment(array $fragment): self
    {
        $decoded = $this->json();
        foreach ($fragment as $key => $value) {
            Assert::assertArrayHasKey($key, $decoded);
            Assert::assertEquals($value, $decoded[$key]);
        }
        return $this;
    }

    public function json(): array
    {
        return json_decode($this->body, true) ?? [];
    }
}
