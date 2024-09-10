<?php 

namespace Eyika\Atom\Framework\Exceptions\Storage;

use RuntimeException;
use Throwable;

class StorageException extends RuntimeException
{
    public function __construct(string $message = 'an error occured in storage operation', int $code = 0, Throwable|null $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
