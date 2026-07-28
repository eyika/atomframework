<?php

namespace Eyika\Atom\Framework\Support\Testing;

use PHPUnit\Framework\Assert;

/**
 * The captured result of a request dispatched through the application by
 * {@see TestCase}. Because responses are captured via BaseResponse::captureOutput()
 * (WRK-02), the status code and headers are available here in addition to the body.
 */
class TestResponse
{
    /** @param string[] $headers Raw "Key: Value" header lines. */
    public function __construct(
        public string $body,
        public ?int $statusCode = null,
        public array $headers = []
    ) {
    }

    public function assertOk(): self
    {
        return $this->assertStatus(200);
    }

    public function assertCreated(): self
    {
        return $this->assertStatus(201);
    }

    public function assertNotFound(): self
    {
        return $this->assertStatus(404);
    }

    public function assertStatus(int $code): self
    {
        Assert::assertSame($code, $this->statusCode, "Expected status {$code}, got {$this->statusCode}.");
        return $this;
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

    /** Assert the decoded JSON body contains the given key/value pairs. */
    public function assertJsonFragment(array $fragment): self
    {
        $decoded = $this->json();
        foreach ($fragment as $key => $value) {
            Assert::assertArrayHasKey($key, $decoded);
            Assert::assertEquals($value, $decoded[$key]);
        }
        return $this;
    }

    /** Assert a header line was sent whose name matches (case-insensitive). */
    public function assertHeader(string $name): self
    {
        $name = strtolower($name);
        $found = false;
        foreach ($this->headers as $line) {
            if (str_starts_with(strtolower($line), $name . ':')) {
                $found = true;
                break;
            }
        }
        Assert::assertTrue($found, "Header \"{$name}\" was not present.");
        return $this;
    }

    /** The decoded JSON body (empty array if the body isn't valid JSON). */
    public function json(): array
    {
        return json_decode($this->body, true) ?? [];
    }

    public function status(): ?int
    {
        return $this->statusCode;
    }
}
