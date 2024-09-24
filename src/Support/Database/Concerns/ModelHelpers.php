<?php

namespace Eyika\Atom\Framework\Support\Database\Concerns;

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

    public function incrementing()
    {
        return $this->incrementing;
    }

    public function exists()
    {
        return $this->exists;
    }

    public function wasRecentlyCreated()
    {
        return $this->wasRecentlyCreated;
    }

    public function __get($name) {
        return $this->dynamicProperties[$name] ?? null;
    }

    public function __set($name, $value) {
        $this->dynamicProperties[$name] = $value;
    }
}