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
        if (!(error_reporting() & $severity)) {
            // Error is suppressed with @ operator
            return;
        }

        throw new ErrorException($message, 0, $severity, $file, $line);
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
        http_response_code(500); // Set appropriate HTTP status code

        // Log the exception (you can replace this with your logger)
        error_log($exception);

        // Display a generic error message (customize as needed)
        echo 'An unexpected error occurred. Please try again later.';

        // Optionally rethrow or terminate
        exit(1);
    }
}
