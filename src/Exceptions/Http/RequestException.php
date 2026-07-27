<?php 

namespace Eyika\Atom\Framework\Exceptions\Http;

use Throwable;

class RequestException extends BaseHttpException
{
    protected $errors;

    public function __construct(string $message = '', int $code = 400, array $errors = [], Throwable|null $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->errors = $errors;
    }

    public function errors(): array
    {
        return $this->errors;
    }
}
