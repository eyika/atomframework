<?php

namespace Eyika\Atom\Framework\Tests\Unit\Exceptions;

use Eyika\Atom\Framework\Exceptions\ErrorHandler;
use PHPUnit\Framework\TestCase;

/**
 * Reported by Claude A (backtestfx), while chasing a 500 with nothing in the log.
 *
 * `handleException()` recorded uncaught throwables with a bare `error_log($exception)`, which
 * writes to PHP's error log — stderr under the dev server, wherever php.ini points under FPM —
 * and so never reached `storage/logs`. Every uncaught throwable produced a 500 whose only trace
 * lived outside the application's own log.
 *
 * It also stringified the whole Throwable, which is unbounded; a single entry of that shape was
 * measured at 185 KB in the field.
 */
class UncaughtExceptionLoggingTest extends TestCase
{
    private ?string $origBasePath = null;
    private string $base;

    protected function setUp(): void
    {
        parent::setUp();

        $this->origBasePath = $GLOBALS['base_path'] ?? null;
        $this->base = sys_get_temp_dir() . '/atom_uncaught_test_' . uniqid();
        @mkdir($this->base . '/storage/logs', 0777, true);
        @mkdir($this->base . '/config', 0777, true);

        // `logger()` resolves config('app.name'), so a base_path without a config directory makes
        // it throw — which is the very hazard the handler guards against, and would leave this
        // test silently exercising only the fallback. Give it a real (throwaway) app to write to.
        foreach (glob(__DIR__ . '/../../Fixtures/app/config/*.php') ?: [] as $config) {
            copy($config, $this->base . '/config/' . basename($config));
        }

        $GLOBALS['base_path'] = $this->base;
    }

    protected function tearDown(): void
    {
        if ($this->origBasePath !== null) {
            $GLOBALS['base_path'] = $this->origBasePath;
        } else {
            unset($GLOBALS['base_path']);
        }

        foreach (glob($this->base . '/storage/logs/*') ?: [] as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    private function loggedContents(): string
    {
        $contents = '';
        foreach (glob($this->base . '/storage/logs/*') ?: [] as $file) {
            $contents .= (string) file_get_contents($file);
        }

        return $contents;
    }

    private function handle(\Throwable $e): void
    {
        ob_start();
        ErrorHandler::handleException($e);
        ob_end_clean();
    }

    /** The reported gap: an uncaught throwable must reach the APPLICATION log. */
    public function test_an_uncaught_exception_reaches_the_application_log(): void
    {
        $this->handle(new \RuntimeException('kaboom'));

        $logged = $this->loggedContents();

        $this->assertNotSame('', $logged, 'nothing was written to storage/logs');
        $this->assertStringContainsString('kaboom', $logged);
        $this->assertStringContainsString('RuntimeException', $logged);
    }

    /** An Error is not an Exception; the handler takes Throwable and must record it too. */
    public function test_an_uncaught_error_reaches_the_application_log(): void
    {
        $this->handle(new \TypeError('bad type'));

        $logged = $this->loggedContents();

        $this->assertStringContainsString('bad type', $logged);
        $this->assertStringContainsString('TypeError', $logged);
    }

    /** The file:line of the throw site is the first thing anyone needs. */
    public function test_the_log_names_the_origin_file_and_line(): void
    {
        $e = new \RuntimeException('located');
        $this->handle($e);

        $logged = $this->loggedContents();

        $this->assertStringContainsString(basename($e->getFile()), $logged);
        $this->assertStringContainsString((string) $e->getLine(), $logged);
    }

    /**
     * The genuinely interesting throwable is often the wrapped one — a PDOException behind a
     * generic wrapper is exactly the case that started this.
     */
    public function test_a_wrapped_cause_is_named_in_the_log(): void
    {
        $cause = new \PDOException("SQLSTATE[42S22]: Unknown column 'compiled_js'");
        $this->handle(new \RuntimeException('query failed', 0, $cause));

        $logged = $this->loggedContents();

        $this->assertStringContainsString('PDOException', $logged);
        $this->assertStringContainsString('compiled_js', $logged);
    }

    /** Traces are capped and argument-free, so one entry cannot run to six figures. */
    public function test_the_logged_trace_is_bounded(): void
    {
        $deep = $this->throwFromDepth(60);
        $this->handle($deep);

        $logged = $this->loggedContents();

        $this->assertStringContainsString('more frames', $logged, 'a deep trace should be truncated');
        $this->assertLessThan(
            50_000,
            strlen($logged),
            'a single uncaught exception should not produce a six-figure log entry'
        );
    }

    /** A broken logger must not swallow the report — that is the failure mode being fixed. */
    public function test_it_falls_back_to_php_error_log_when_the_app_logger_is_unusable(): void
    {
        // Point base_path somewhere unwritable so the application logger cannot be built.
        $GLOBALS['base_path'] = $this->base . '/does/not/exist';

        $errorLogFile = $this->base . '/php-error.log';
        $originalDest = ini_get('error_log');
        ini_set('error_log', $errorLogFile);

        try {
            $this->handle(new \RuntimeException('fallback path'));
        } finally {
            ini_set('error_log', $originalDest === false ? '' : $originalDest);
        }

        $this->assertStringContainsString(
            'fallback path',
            (string) @file_get_contents($errorLogFile),
            'a failing application logger must not lose the exception'
        );
    }

    /** The handler must never throw — it is the last line of defence. */
    public function test_the_handler_never_throws_even_with_an_unusable_logger(): void
    {
        // No config directory → logger() raises while resolving config('app.name'), which is the
        // WRK-10 hazard: an error arising before config is loadable must not fatal *inside* the
        // error handler.
        $GLOBALS['base_path'] = $this->base . '/no/app/here';

        $originalDest = ini_get('error_log');
        ini_set('error_log', $this->base . '/php-error.log');

        try {
            $this->handle(new \RuntimeException('still fine'));
        } finally {
            ini_set('error_log', $originalDest === false ? '' : $originalDest);
        }

        $this->assertTrue(true, 'reached here → handleException swallowed its own logging failure');
    }

    private function throwFromDepth(int $depth): \Throwable
    {
        if ($depth <= 0) {
            return new \RuntimeException('deep');
        }

        return $this->throwFromDepth($depth - 1);
    }
}
