<?php 

namespace Eyika\Atom\Framework\Exceptions\Http;

use Eyika\Atom\Framework\Exceptions\BaseHttpException;
use Throwable;

class ResponseException extends BaseHttpException
{
    public function __construct(string $message = '', int $code = 0, Throwable|null $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
