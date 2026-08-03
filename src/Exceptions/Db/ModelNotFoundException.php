<?php

namespace Eyika\Atom\Framework\Exceptions\Db;

use Eyika\Atom\Framework\Exceptions\BaseException;

final class ModelNotFoundException extends BaseException
{
    protected array $errors;

    /**
     * $errors previously had no default while $message did — a required parameter after an
     * optional one, which PHP deprecates and which made the one-argument call in
     * SubstituteBindings fatal with ArgumentCountError instead of raising this exception.
     */
    public function __construct($message = 'model not found', array $errors = [])
    {
       parent::__construct($message);
       $this->errors = $errors;
    }

    public function errors(): array
    {
        return $this->errors;
    }
}
