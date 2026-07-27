<?php

namespace Eyika\Atom\Framework\Support;

abstract class ValidatorRule
{
    public string $error;

    // Abstract so a subclass MUST implement it (compile-time error), rather than
    // inheriting a runtime-throwing stub.
    abstract public function passes(string $value): bool;

    public function getError(): string
    {
        return $this->error;
    }
}