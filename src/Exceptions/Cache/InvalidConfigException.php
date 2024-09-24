<?php 

namespace Eyika\Atom\Framework\Exceptions\Cache;

use RuntimeException;
use Throwable;

class InvalidConfigException extends RuntimeException
{
    public function __construct(string $message = 'your configuration is invalid', int $code = 0, Throwable|null $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
