<?php

namespace Eyika\Atom\Framework\Exceptions\Db;

use Eyika\Atom\Framework\Exceptions\BaseException;

final class ModelNotFoundException extends BaseException
{
    protected array $errors;

    public function __construct($message = 'model not found', array $errors)
    {
       parent::__construct($message);
       $this->errors = $errors;
    }

    public function errors(): array
    {
        return $this->errors;
    }
}
