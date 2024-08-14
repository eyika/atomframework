<?php

namespace Basttyy\FxDataServer\libs\Traits;

trait ModelHelpers
{
    use ModelProperties;

    public function isSaved()
    {
        if ($this->child->{$this->child->primaryKey} == null)
            return false;

        return true;
    }

    public function isNotSaved()
    {
        return !$this->isSaved();
    }

    public function __get($name) {
        return $this->dynamicProperties[$name] ?? null;
    }

    public function __set($name, $value) {
        $this->dynamicProperties[$name] = $value;
    }
}