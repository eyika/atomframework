<?php 

namespace Eyika\Atom\Framework\Exceptions\Http;

use Throwable;

class NotFoundHttpException extends BaseHttpException
{
    public function __construct(string $message = '', int $code = 404, Throwable|null $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
