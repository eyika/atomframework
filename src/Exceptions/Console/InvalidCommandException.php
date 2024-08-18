<?php 

namespace Eyika\Atom\Framework\Exceptions\Console;

use Throwable;

class InvalidCommandException extends BaseConsoleException
{
    public function __construct(string $message = '', int $code = 0, Throwable|null $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
