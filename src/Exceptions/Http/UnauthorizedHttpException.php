<?php 

namespace Eyika\Atom\Framework\Exceptions\Http;

use Throwable;

class UnauthorizedHttpException extends BaseHttpException
{
    public function __construct(string $message = '', int $code = 401, Throwable|null $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    public function getStatusCode()
    {
        return $this->getCode();
    }
}
