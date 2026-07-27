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
        ob_start();
        ErrorHandler::handleException(new \RuntimeException('boom'));
        $output = ob_get_clean();

        $this->assertStringContainsString('unexpected error', $output);
        $this->assertTrue(true, 'reached here → handleException did not exit()');
    }
}
