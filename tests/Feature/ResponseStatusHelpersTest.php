<?php

namespace Eyika\Atom\Framework\Tests\Feature;

use Eyika\Atom\Framework\Http\BaseResponse;
use Eyika\Atom\Framework\Http\JsonResponse;
use Eyika\Atom\Framework\Http\Response;
use Exception;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Covers the 429/503 helpers and the `Response::json()` status validation.
 *
 * `json()` used to validate the requested status against METHOD_TO_FUNC — the index of codes
 * that happen to have a named shorthand. That made `json($data, 409)` throw "Invalid HTTP status
 * code" even though 409 is a framework constant WITH a `conflict()` helper, and blocked every
 * redirect code plus 502/503 outright.
 */
class ResponseStatusHelpersTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->bindRequest('GET', '/');
        BaseResponse::captureOutput(true);
    }

    protected function tearDown(): void
    {
        BaseResponse::captureOutput(false);
        parent::tearDown();
    }

    public function test_too_many_requests_emits_429(): void
    {
        $response = (new JsonResponse())->tooManyRequests('slow down');
        $response->send();

        $this->assertSame(429, $response->sentStatus());
        $this->assertStringContainsString('slow down', $response->sentBody());
    }

    public function test_too_many_requests_sets_retry_after_when_given(): void
    {
        $response = (new JsonResponse())->tooManyRequests('slow down', 60);
        $response->send();

        $this->assertSame('60', $this->headerValue($response, 'Retry-After'));
    }

    public function test_too_many_requests_omits_retry_after_when_not_given(): void
    {
        $response = (new JsonResponse())->tooManyRequests('slow down');
        $response->send();

        $this->assertNull($this->headerValue($response, 'Retry-After'));
    }

    /** A negative window is meaningless to a client; clamp rather than emit `Retry-After: -5`. */
    public function test_negative_retry_after_is_clamped_to_zero(): void
    {
        $response = (new JsonResponse())->tooManyRequests('slow down', -5);
        $response->send();

        $this->assertSame('0', $this->headerValue($response, 'Retry-After'));
    }

    public function test_service_unavailable_emits_503_with_retry_after(): void
    {
        $response = (new JsonResponse())->serviceUnavailable('maintenance', 120);
        $response->send();

        $this->assertSame(503, $response->sentStatus());
        $this->assertSame('120', $this->headerValue($response, 'Retry-After'));
    }

    /**
     * Every one of these has a framework constant, and 409/502 even had a helper — yet `json()`
     * rejected them all because they were absent from METHOD_TO_FUNC.
     */
    #[DataProvider('validStatusProvider')]
    public function test_json_accepts_valid_status_codes(int $status): void
    {
        $response = (new Response())->json(['message' => 'x'], $status);
        $response->send();

        $this->assertSame($status, $response->sentStatus());
    }

    public static function validStatusProvider(): array
    {
        return [
            'conflict (has a helper, was rejected)' => [409],
            'too many requests' => [429],
            'bad gateway (has a helper, was rejected)' => [502],
            'service unavailable' => [503],
            'found / redirect' => [302],
            'method not allowed (no helper, still valid)' => [405],
            'ok' => [200],
        ];
    }

    #[DataProvider('invalidStatusProvider')]
    public function test_json_still_rejects_non_http_status_codes(int $status): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Invalid HTTP status code: $status");

        (new Response())->json(['message' => 'x'], $status);
    }

    public static function invalidStatusProvider(): array
    {
        return [
            'zero' => [0],
            'below the 1xx floor' => [99],
            'above the 5xx ceiling' => [600],
            'negative' => [-1],
        ];
    }

    /**
     * Middleware wrapping a handler must be able to ask whether it succeeded. `status()` is a
     * setter and `$statusCode` is protected, so before `getStatusCode()` that was only observable
     * after `send()`.
     */
    public function test_status_code_is_readable_before_the_response_is_sent(): void
    {
        $response = (new JsonResponse())->tooManyRequests('slow down');

        $this->assertSame(429, $response->getStatusCode());
        $this->assertNull($response->sentStatus(), 'nothing sent yet');
    }

    public function test_status_code_reflects_an_explicit_status_call(): void
    {
        $response = (new Response())->status(418);

        $this->assertSame(418, $response->getStatusCode());
    }

    public function test_status_code_defaults_to_200(): void
    {
        $this->assertSame(200, (new Response())->getStatusCode());
    }

    /** Every code in the index must name a method that really exists (STATUS_NOT_MODIFIED did not). */
    public function test_status_to_helper_index_only_names_real_methods(): void
    {
        $reflection = new \ReflectionClass(BaseResponse::class);
        $map = $reflection->getConstant('METHOD_TO_FUNC');

        foreach ($map as $status => $method) {
            $this->assertTrue(
                method_exists(JsonResponse::class, $method) || method_exists(Response::class, $method),
                "METHOD_TO_FUNC maps $status to '$method', which is not defined on either response class"
            );
        }
    }

    /** Standing rule 1: the facade's @method list must not drift from the class it fronts. */
    public function test_facade_docblock_lists_every_json_response_helper(): void
    {
        $docblock = (new \ReflectionClass(\Eyika\Atom\Framework\Support\Facade\JsonResponse::class))
            ->getDocComment();

        foreach ((new \ReflectionClass(JsonResponse::class))->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getDeclaringClass()->getName() !== JsonResponse::class) {
                continue; // inherited from BaseResponse — not part of the helper surface
            }

            $this->assertStringContainsString(
                $method->getName() . '(',
                (string) $docblock,
                "JsonResponse::{$method->getName()}() is missing from the facade @method docblock"
            );
        }
    }

    private function headerValue(BaseResponse $response, string $name): ?string
    {
        foreach ($response->sentHeaders() as $key => $value) {
            // sentHeaders() may be keyed by name or hold raw "Name: value" strings.
            if (is_string($key) && strcasecmp($key, $name) === 0) {
                return (string) $value;
            }
            if (is_string($value) && stripos($value, $name . ':') === 0) {
                return trim(substr($value, strlen($name) + 1));
            }
        }

        return null;
    }
}
