<?php

namespace Eyika\Atom\Framework\Support\Auth\Exceptions;

use Exception;
use RuntimeException;

final class BadTokenException extends RuntimeException
{
    public function __construct(Exception $previous)
    {
        parent::__construct($previous->getMessage(), $previous->getCode(), $previous);
    }
}
