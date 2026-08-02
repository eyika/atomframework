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

        error_log($exception);

        echo 'An unexpected error occurred. Please try again later.';

        // Do NOT exit() here (WRK-10): under a persistent worker that would kill the
        // whole process and drop every subsequent request. Return so the worker can
        // finish this response and serve the next request. (Under FPM the script ends
        // after this handler anyway.)
    }
}
