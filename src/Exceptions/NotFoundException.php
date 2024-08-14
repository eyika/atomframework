<?php

namespace Basttyy\FxDataServer\Exceptions;

use Eyika\Atom\Framework\Exceptions\BaseException;

final class NotFoundException extends BaseException
{
    public function __construct($message = 'model not found')
    {
       parent::__construct($message);
    }
}
