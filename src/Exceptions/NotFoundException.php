<?php

namespace Basttyy\FxDataServer\Exceptions;

use RuntimeException;

final class NotFoundException extends RuntimeException
{
    public function __construct($message = 'model not found')
    {
       parent::__construct($message);
    }
}