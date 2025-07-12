<?php 

namespace Eyika\Atom\Framework\Exceptions;

use RuntimeException;
use Throwable;

class ValidationException extends BaseException
{
    protected array $errors;

    public function __construct(array $errors, string $message = 'validation failed', int $code = 422, Throwable|null $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->errors = $errors;
    }

    public function errors(): array
    {
        return $this->errors;
    }
}
