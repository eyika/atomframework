<?php

namespace Eyika\Atom\Framework\Exceptions;

use Eyika\Atom\Framework\Exceptions\BaseException;

final class NotFoundException extends BaseException
{
    public function __construct($message = 'not found')
    {
       parent::__construct($message);
    }
}
