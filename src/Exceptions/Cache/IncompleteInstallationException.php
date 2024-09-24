<?php 

namespace Eyika\Atom\Framework\Exceptions\Cache;

use RuntimeException;
use Throwable;

class IncompleteInstallationException extends RuntimeException
{
    public function __construct(string $message = 'the required library or plugin is not installed', int $code = 0, Throwable|null $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
