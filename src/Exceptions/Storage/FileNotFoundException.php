<?php 

namespace Eyika\Atom\Framework\Exceptions\Storage;

use RuntimeException;
use Throwable;

class FileNotFoundException extends RuntimeException
{
    public function __construct(string $message = 'file does not exist in given path', int $code = 0, Throwable|null $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
