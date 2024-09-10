<?php 

namespace Eyika\Atom\Framework\Exceptions\Storage;

use RuntimeException;
use Throwable;

class InvalidDiskException extends RuntimeException
{
    public function __construct(string $message = 'selected disk does not exist in filesystem config', int $code = 0, Throwable|null $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
