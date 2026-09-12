<?php

namespace Eyika\Atom\Framework\Tests\Unit\Exceptions;

use Eyika\Atom\Framework\Exceptions\Http\NotFoundHttpException;
use Eyika\Atom\Framework\Foundation\ExceptionHandler;
use Eyika\Atom\Framework\Http\BaseResponse;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use RuntimeException;

/**
 * Reported by a downstream consumer, as an aside while reporting a missing asset type: the
 * framework's error page was served at **HTTP 200**.
 *
 * That is how a 1.8MB video read as a 210KB "success" in their status column — the request fell
 * through to the router, raised `NotFoundHttpException`, and the error page came back 200
 * `text/html`. Anything that reads the status rather than the body — a health check, a link
 * checker, a build step, a QA probe — sees success.
 *
 * The cause was `response()->html($page)`, whose second parameter defaults to `STATUS_OK`. The
 * non-debug branch had a second defect in the same expression: `$exception->getCode() ?? 500`
 * never fires the `??`, because `getCode()` returns an int and never null — so a generic exception
 * carrying code 0 produced a response with status 0.
 */
class ErrorPageStatusTest extends TestCase
{
    private function statusFor(\Throwable $e): int
    {
        $method = new ReflectionMethod(ExceptionHandler::class, 'statusFor');
        $method->setAccessible(true);

        return $method->invoke(null, $e);
    }

    /** An HTTP exception carries its own status; use it rather than defaulting. */
    public function test_an_http_exception_keeps_its_status(): void
    {
        $this->assertSame(
            BaseResponse::STATUS_NOT_FOUND,
            $this->statusFor(new NotFoundHttpException('Route not found'))
        );
    }

    /** A generic exception has no meaningful code, so the page is a server error. */
    public function test_a_generic_exception_becomes_a_server_error(): void
    {
        $this->assertSame(BaseResponse::STATUS_INTERNAL_SERVER_ERROR, $this->statusFor(new RuntimeException('boom')));
    }

    /**
     * The `??` trap: `getCode()` returns int, so it never yields null and the fallback never ran.
     * A code of 0 used to become the response status.
     */
    public function test_a_zero_code_does_not_become_the_status(): void
    {
        $status = $this->statusFor(new RuntimeException('boom', 0));

        $this->assertNotSame(0, $status, 'a code of 0 leaked through as the HTTP status');
        $this->assertSame(BaseResponse::STATUS_INTERNAL_SERVER_ERROR, $status);
    }

    /**
     * Exception codes are not HTTP statuses in general — a PDOException carries SQLSTATE, an
     * application exception carries whatever it likes. Only a plausible HTTP status is trusted.
     */
    public function test_a_code_that_is_not_an_http_status_is_ignored(): void
    {
        $this->assertSame(BaseResponse::STATUS_INTERNAL_SERVER_ERROR, $this->statusFor(new RuntimeException('x', 42)));
        $this->assertSame(BaseResponse::STATUS_INTERNAL_SERVER_ERROR, $this->statusFor(new RuntimeException('x', 1054)));
        $this->assertSame(BaseResponse::STATUS_INTERNAL_SERVER_ERROR, $this->statusFor(new RuntimeException('x', 200)));
    }

    public function test_a_server_side_http_code_is_kept(): void
    {
        $this->assertSame(503, $this->statusFor(new RuntimeException('down', 503)));
    }
}
