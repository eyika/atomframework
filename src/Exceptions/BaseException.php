<?php 

namespace Eyika\Atom\Framework\Exceptions;

use RuntimeException;
use Throwable;

class BaseException extends RuntimeException
{
    public function __construct(string $message = '', int $code = 0, Throwable|null $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
