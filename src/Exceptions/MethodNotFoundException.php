<?php

namespace Eyika\Atom\Framework\Exceptions;

use Eyika\Atom\Framework\Exceptions\BaseException;

final class MethodNotFoundException extends BaseException
{
    public function __construct($message = 'method not found')
    {
       parent::__construct($message);
    }
}
