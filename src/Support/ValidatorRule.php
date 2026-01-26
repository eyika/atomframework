<?php

namespace Eyika\Atom\Framework\Support;

use Eyika\Atom\Framework\Exceptions\NotImplementedException;

abstract class ValidatorRule
{
    public string $error;

    public function passes(string $value): bool
    {
        throw new NotImplementedException('this method was not implemented, you should implement it');
    }

    public function getError(): string
    {
        throw new NotImplementedException('this method was not implemented, you should implement it');
    }
}