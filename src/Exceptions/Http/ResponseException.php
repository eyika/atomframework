<?php 

namespace Eyika\Atom\Exceptions\Http;

use Eyika\Atom\Exceptions\BaseHttpException;
use Throwable;

class ResponseException extends BaseHttpException
{
    public function __construct(string $message = '', int $code = 0, Throwable|null $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
