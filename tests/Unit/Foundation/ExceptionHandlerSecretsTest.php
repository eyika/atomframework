<?php

namespace Eyika\Atom\Framework\Tests\Unit\Foundation;

use Eyika\Atom\Framework\Foundation\ExceptionHandler;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;
use Throwable;

/**
 * The debug error response used to include $exception->getTrace() with per-frame args —
 * live values that carry secrets (the Request's server bag = the whole environment). This
 * is the leak that printed a GITHUB_PAT to the terminal. sanitizeTrace() must strip args.
 */
class ExceptionHandlerSecretsTest extends TestCase
{
    private function sanitize(Throwable $e): array
    {
        $ref = new ReflectionClass(ExceptionHandler::class);
        $instance = $ref->newInstanceWithoutConstructor();
        $method = $ref->getMethod('sanitizeTrace');
        $method->setAccessible(true);

        return $method->invoke($instance, $e);
    }

    public function test_sanitized_trace_strips_args_and_does_not_leak_a_secret(): void
    {
        $secret = 'github_pat_11ABCDEFTOPSECRETVALUE';

        $leak = function (string $token) {
            throw new RuntimeException('boom');
        };

        try {
            $leak($secret);
            $this->fail('expected an exception');
        } catch (Throwable $e) {
            // Sanity: the RAW trace really does carry the secret in a frame's args.
            $this->assertStringContainsString($secret, json_encode($e->getTrace()));

            $sanitized = $this->sanitize($e);

            // Every frame has had its args dropped …
            foreach ($sanitized as $frame) {
                $this->assertArrayNotHasKey('args', $frame);
            }
            // … so the secret is gone, while frame metadata is preserved.
            $this->assertStringNotContainsString($secret, json_encode($sanitized));
            $this->assertArrayHasKey('function', $sanitized[0]);
        }
    }
}
