<?php

namespace Eyika\Atom\Framework\Tests\Unit\Exceptions;

use Eyika\Atom\Framework\Exceptions\ErrorHandler;
use PHPUnit\Framework\TestCase;

/**
 * Covers WRK-10: ErrorHandler::handleException() used to exit(1), which under a
 * persistent worker kills the whole process and drops every subsequent request. It
 * must return instead. (Reaching the assertions below at all proves it did not exit —
 * an exit() would take the phpunit process down with it.)
 */
class ErrorHandlerTest extends TestCase
{
    public function test_handle_exception_returns_instead_of_exiting(): void
    {
        // handleException() now writes to the application log, and the default base_path is the
        // fixture app — so point it at a throwaway directory rather than leaving log residue in
        // the repo. (Logging itself is covered by UncaughtExceptionLoggingTest.)
        $origBasePath = $GLOBALS['base_path'] ?? null;
        $GLOBALS['base_path'] = sys_get_temp_dir() . '/atom_errorhandler_exit_' . uniqid();

        try {
            ob_start();
            ErrorHandler::handleException(new \RuntimeException('boom'));
            $output = ob_get_clean();
        } finally {
            if ($origBasePath !== null) {
                $GLOBALS['base_path'] = $origBasePath;
            } else {
                unset($GLOBALS['base_path']);
            }
        }

        $this->assertStringContainsString('unexpected error', $output);
        $this->assertTrue(true, 'reached here → handleException did not exit()');
    }

    /**
     * handleError() carried four leftover `logger()->info('got here now …')` debug calls, the
     * first of them BEFORE the reportability check — so every `@`-suppressed operation built a
     * Monolog logger, read config and wrote to storage/logs. Since it is registered as PHP's
     * error handler, that fired on every notice/warning/deprecation.
     *
     * Pointing base_path at an empty temp dir makes any write observable: a clean run leaves the
     * log directory empty.
     */
    public function test_handle_error_does_not_log(): void
    {
        $origBasePath = $GLOBALS['base_path'] ?? null;
        $base = sys_get_temp_dir() . '/atom_errorhandler_test_' . uniqid();
        @mkdir($base . '/storage/logs', 0777, true);
        $GLOBALS['base_path'] = $base;

        $origReporting = error_reporting();

        try {
            // Severity masked out of error_reporting() → the suppressed-error early return.
            // (PHPUnit installs its own handler and mask, so set the one under test explicitly.)
            error_reporting(0);
            ErrorHandler::handleError(E_USER_WARNING, 'suppressed', __FILE__, __LINE__);

            // A reportable severity still converts to an ErrorException, and still must not log.
            error_reporting(E_ALL);
            try {
                ErrorHandler::handleError(E_USER_WARNING, 'reportable', __FILE__, __LINE__);
                $this->fail('a reportable severity should convert to an ErrorException');
            } catch (\ErrorException $e) {
                $this->assertSame('reportable', $e->getMessage());
            }

            $this->assertSame(
                [],
                glob($base . '/storage/logs/*') ?: [],
                'handleError() must not write log files'
            );
        } finally {
            error_reporting($origReporting);
            foreach (glob($base . '/storage/logs/*') ?: [] as $f) {
                @unlink($f);
            }
            @rmdir($base . '/storage/logs');
            @rmdir($base . '/storage');
            @rmdir($base);
            if ($origBasePath === null) {
                unset($GLOBALS['base_path']);
            } else {
                $GLOBALS['base_path'] = $origBasePath;
            }
        }
    }

    public function test_handle_error_converts_deprecations_to_exceptions(): void
    {
        $origReporting = error_reporting(E_ALL);

        try {
            $this->expectException(\ErrorException::class);
            ErrorHandler::handleError(E_USER_DEPRECATED, 'deprecated thing', __FILE__, __LINE__);
        } finally {
            error_reporting($origReporting);
        }
    }
}
