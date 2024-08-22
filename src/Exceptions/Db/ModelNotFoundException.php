<?php

namespace Eyika\Atom\Framework\Exceptions\Db;

use Eyika\Atom\Framework\Exceptions\BaseException;

final class ModelNotFoundException extends BaseException
{
    public function __construct($message = 'model not found')
    {
       parent::__construct($message);
    }
}
