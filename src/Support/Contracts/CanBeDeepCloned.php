<?php

namespace Eyika\Atom\Framework\Support\Contracts;

interface CanBeDeepCloned
{
    /**
     * Deep clone the object and its object based properties.
     *
     * @return $this
     */
    public function __clone();
}
