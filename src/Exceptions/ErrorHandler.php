<?php

namespace Eyika\Atom\Framework\Exceptions;

use ErrorException;

class ErrorHandler
{
    /**
     * Register the error handler.
     */
    public static function register(): void
    {
        // Convert PHP warnings, notices, and errors to exceptions
        set_error_handler([self::class, 'handleError']);

        // Handle fatal errors at script shutdown
        register_shutdown_function([self::class, 'handleShutdown']);

        // Ensure exceptions are caught and displayed
        set_exception_handler([self::class, 'handleException']);
    }

    /**
     * Custom error handler: Convert PHP errors to ErrorException.
     *
     * @param int    $severity
     * @param string $message
     * @param string $file
     * @param int    $line
     * @throws ErrorException
     */
    public static function handleError(int $severity, string $message, string $file, int $line): void
    {
        // Check if the error is reportable
        if (!(error_reporting() & $severity)) {
            return; // Suppressed with @ or not included in error_reporting()
        }

        // Convert deprecation warnings to exceptions explicitly
        if (in_array($severity, [E_DEPRECATED, E_USER_DEPRECATED], true)) {
            throw new \ErrorException($message, 0, $severity, $file, $line);
        }

        // Handle other errors
        throw new \ErrorException($message, 0, $severity, $file, $line);
    }

    /**
     * Handle fatal errors during script shutdown.
     */
    public static function handleShutdown(): void
    {
        $error = error_get_last();

        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            // Convert fatal error to exception
            self::handleException(
                new ErrorException($error['message'], 0, $error['type'], $error['file'], $error['line'])
            );
        }
    }

    /**
     * Handle uncaught exceptions.
     *
     * @param \Throwable $exception
     */
    public static function handleException(\Throwable $exception): void
    {
        if (!headers_sent()) {
            http_response_code(500);
        }

        self::report($exception);

        echo 'An unexpected error occurred. Please try again later.';

        // Do NOT exit() here (WRK-10): under a persistent worker that would kill the
        // whole process and drop every subsequent request. Return so the worker can
        // finish this response and serve the next request. (Under FPM the script ends
        // after this handler anyway.)
    }

    /**
     * Record an uncaught throwable to the application log, and a one-line breadcrumb to PHP's.
     *
     * This used to be a bare `error_log($exception)`, which writes to PHP's error log — stderr
     * under the dev server, wherever php.ini points under FPM — and so **never** reached
     * `storage/logs`. Every uncaught throwable therefore produced a 500 whose only trace lived
     * outside the application's own log, which is a dead end precisely when you most need one.
     *
     * The application logger is tried FIRST but always defensively: `logger()` resolves config,
     * and an error raised before config is loadable would otherwise fatal inside the error
     * handler itself (WRK-10). Any failure there falls back to PHP's log with the full detail, so
     * a broken logger can never swallow the report entirely.
     *
     * The single line still sent to PHP's log keeps `php -S` and `docker logs` useful without
     * duplicating a whole stack trace into two sinks — stringifying a Throwable is unbounded, and
     * has produced six-figure log entries in the field.
     */
    protected static function report(\Throwable $exception): void
    {
        $summary = self::summarize($exception);

        try {
            if (function_exists('logger')) {
                logger()->error($summary, [
                    'exception' => get_class($exception),
                    'file'      => $exception->getFile(),
                    'line'      => $exception->getLine(),
                    'trace'     => self::compactTrace($exception),
                ]);

                error_log($summary); // breadcrumb only — the detail is in the app log
                return;
            }
        } catch (\Throwable $loggingFailure) {
            // Fall through: the application logger is unavailable or itself broken (no config
            // yet, unwritable storage/logs, a misconfigured channel). Never let that hide the
            // original problem, and never throw from here.
        }

        error_log($summary . "\n" . implode("\n", self::compactTrace($exception)));
    }

    /** `Class: message in /file.php:12`, plus the wrapped cause when there is one. */
    protected static function summarize(\Throwable $exception): string
    {
        $summary = sprintf(
            '%s: %s in %s:%d',
            get_class($exception),
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine()
        );

        // The interesting throwable is often the wrapped one — a PDOException behind a generic
        // wrapper, say — so name the chain rather than only its outermost link.
        $previous = $exception->getPrevious();
        while ($previous !== null) {
            $summary .= sprintf(
                ' <- %s: %s in %s:%d',
                get_class($previous),
                $previous->getMessage(),
                $previous->getFile(),
                $previous->getLine()
            );
            $previous = $previous->getPrevious();
        }

        return $summary;
    }

    /**
     * `file:line function()` frames, capped and without arguments.
     *
     * Argument values are what make a trace enormous (and are the reason a trace can leak
     * credentials passed to a connect() call into the log), so they are dropped entirely.
     */
    protected static function compactTrace(\Throwable $exception, int $limit = 20): array
    {
        $frames = [];
        $trace = $exception->getTrace();

        foreach (array_slice($trace, 0, $limit) as $frame) {
            $frames[] = sprintf(
                '%s:%s %s%s%s()',
                $frame['file'] ?? '[internal]',
                $frame['line'] ?? '?',
                $frame['class'] ?? '',
                $frame['type'] ?? '',
                $frame['function'] ?? '?'
            );
        }

        $omitted = count($trace) - count($frames);
        if ($omitted > 0) {
            $frames[] = "... $omitted more frames";
        }

        return $frames;
    }
}
