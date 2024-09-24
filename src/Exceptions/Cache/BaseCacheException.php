<?php 

namespace Eyika\Atom\Framework\Exceptions\Cache;

use RuntimeException;
use Throwable;

class BaseCacheException extends RuntimeException
{
    public function __construct(string $message = '', int $code = 0, Throwable|null $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
