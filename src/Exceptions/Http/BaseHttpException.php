<?php 

namespace Eyika\Atom\Framework\Exceptions;

use Throwable;

class BaseHttpException extends BaseException
{
    public function __construct(string $message = '', int $code = 0, Throwable|null $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
