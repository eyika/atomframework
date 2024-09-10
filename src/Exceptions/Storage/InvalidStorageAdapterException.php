<?php 

namespace Eyika\Atom\Framework\Exceptions\Storage;

use RuntimeException;
use Throwable;

class InvalidStorageAdapterException extends RuntimeException
{
    public function __construct(string $message = 'given callback must return a valid flysystem adapter instance', int $code = 0, Throwable|null $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
